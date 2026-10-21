"use client";
import { useEffect, useRef, useState } from "react";
import { api } from "@/lib/api";
import { campaign, recordView } from "@/lib/analytics";
import type { V3Answer, V3Bootstrap, V3Run, V3Screen } from "@/lib/v3";
import { Privacy } from "./privacy";
import { V3Quiz } from "./v3-quiz";
import { V3Commerce } from "./v3-commerce";
import { prepareScreenAssets, screenAssetsReady, warmFunnelAssets, warmUpcomingAssets } from "@/lib/v3-assets";

function allowed(run: V3Run, id: string) {
  return id === run.current_step || run.completed_steps.includes(id);
}
function requested(run: V3Run) {
  const id = new URLSearchParams(window.location.search).get("screen");
  return id && allowed(run, id) ? id : run.current_step;
}
function finalScreen(run: V3Run) {
  return run.configuration.steps.find(s => s.type === "final" && !!s.guide === (run.commerce.upsell === "accept"));
}
function elapsed(started: string | undefined, now: number) {
  const seconds = started ? Math.max(0, Math.floor((now - Date.parse(started)) / 1000)) : 0;
  return `${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`;
}
export function V3Funnel({ initial }: { initial: V3Bootstrap }) {
  const [run, setRun] = useState<V3Run | null>(initial.run);
  const [view, setView] = useState(initial.run?.current_step || initial.configuration.entry);
  const [busy, setBusy] = useState(!initial.run);
  const [error, setError] = useState("");
  const [pending, setPending] = useState<{ step: string; answer: V3Answer } | null>(null);
  const [navigating, setNavigating] = useState(false);
  const [imageFailure, setImageFailure] = useState<{ step: string; message: string } | null>(null);
  const navigation = useRef(0);
  const observedViews = useRef(new Map<string, Parameters<typeof recordView>[0]>());
  const deliveringView = useRef(false);
  const [now, setNow] = useState(() => Date.now());
  const [deleted, setDeleted] = useState(false);
  const initialized = useRef(false);
  const writing = useRef(false);
  const runRef = useRef(initial.run);
  const viewRef = useRef(view);
  const headingRegion = useRef<HTMLDivElement>(null);
  const errorRegion = useRef<HTMLDivElement>(null);
  const information = useRef<HTMLDialogElement>(null);
  function show(id: string, replace = false) {
    const token = ++navigation.current;
    const current = runRef.current;
    const configuration = current?.configuration || initial.configuration;
    const target = id === "done" && current ? finalScreen(current) : configuration.steps.find(screen => screen.id === id);
    viewRef.current = id;
    setImageFailure(null);
    const present = () => {
      if (token !== navigation.current) return;
      setView(id); setNavigating(false);
      const url = new URL(window.location.href);
      url.searchParams.set("screen", id);
      if (replace) window.history.replaceState(null, "", url);
      else window.history.pushState(null, "", url);
    };
    if (screenAssetsReady(target)) { present(); return; }
    setNavigating(true);
    // Present one complete question, never text first and illustrations later.
    void prepareScreenAssets(target).then(present, (failure: Error) => {
      if (token !== navigation.current) return;
      setNavigating(false); setImageFailure({ step: id, message: failure.message });
    });
  }
  function accept(value: V3Run) { runRef.current = value; setRun(value); }
  async function flushViews() {
    const current = runRef.current;
    if (!current || writing.current || deliveringView.current) return;
    const next = [...observedViews.current.entries()].find(([, event]) => event.run_id === current.id && !!event.step_id && allowed(current, event.step_id));
    if (!next) return;
    const [key, event] = next;
    observedViews.current.delete(key); deliveringView.current = true;
    try { await recordView(event); }
    finally { deliveringView.current = false; void flushViews(); }
  }
  function observe(name: Parameters<typeof recordView>[0]["name"], id: string) {
    const current = runRef.current;
    if (!current) return;
    observedViews.current.set(`${name}:${id}`, { name, run_id: current.id, step_id: id });
    void flushViews();
  }
  async function start() {
    if (writing.current) return;
    writing.current = true; setBusy(true); setError("");
    try {
      const value = await api<V3Run>("runs", { attribution: campaign() });
      accept(value); show(requested(value), true);
    } catch (e) { setError((e as Error).message || "Your challenge could not start. Please try again."); }
    finally { writing.current = false; setBusy(false); }
  }
  async function restore() {
    if (writing.current) return;
    writing.current = true; setBusy(true); setError("");
    try {
      const value = await api<V3Bootstrap>("bootstrap");
      if (value.run) { accept(value.run); setPending(null); show(value.run.current_step, true); }
      else window.location.replace("/");
    } catch (e) { setError((e as Error).message || "Your progress could not be restored. Please try again."); }
    finally { writing.current = false; setBusy(false); }
  }
  async function save(answer: V3Answer, step = viewRef.current) {
    const current = runRef.current;
    if (!current || writing.current || current.demo_completed || step !== current.current_step
      || navigating || imageFailure
      || (viewRef.current !== step && pending?.step !== step)) return;
    const source = current.configuration.steps.find(screen => screen.id === step);
    if (!source) return;
    // Validate against this question before any optimistic navigation.
    if ((source.type === "single_choice" || source.type === "scale")
      && (!("choice" in answer) || !source.options?.some(option => option.id === answer.choice))) return;
    // Only deterministic, noncommercial presentation can lead server confirmation.
    // The next answer stays locked until this write succeeds; private report/offer state is never projected.
    const target = current.configuration.steps.find(screen => screen.id === source?.next);
    const preview = source && target && ["word_intro", "single_choice", "scale", "timed_reveal", "interstitial"].includes(source.type)
      && !["paywall", "upsell", "final"].includes(target.type) ? target.id : null;
    writing.current = true; setBusy(true); setError(""); setPending({ step, answer });
    if (preview) show(preview);
    try {
      const value = await api<V3Run>(`runs/${current.id}/steps/${encodeURIComponent(step)}/complete`, { answer });
      accept(value); setPending(null);
      if (!preview || value.current_step !== preview || viewRef.current === step) show(value.current_step);
    } catch (e) {
      setError((e as Error).message || "Your answer could not be saved. Retry when you are connected.");
      // Return to the exact unsaved answer, including an identical retry after a lost response.
      if (preview) show(step, true);
    } finally { writing.current = false; setBusy(false); void flushViews(); }
  }
  useEffect(() => {
    if (initialized.current) return;
    initialized.current = true;
    try { setDeleted(sessionStorage.getItem("brainsprint-data-deleted") === "1"); sessionStorage.removeItem("brainsprint-data-deleted"); } catch {}
    if (initial.run) show(requested(initial.run), true);
    else void start();
    // Initialization is once per mounted visitor, including React strict effects.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    const interval = window.setInterval(() => setNow(Date.now()), 1000);
    function pop() {
      const current = runRef.current;
      if (current) show(writing.current ? viewRef.current : requested(current), true);
    }
    window.addEventListener("popstate", pop);
    return () => { clearInterval(interval); window.removeEventListener("popstate", pop); };
  }, []);
  useEffect(() => {
    if (navigating) return;
    const heading = headingRegion.current?.querySelector<HTMLElement>("h1");
    heading?.setAttribute("tabindex", "-1"); heading?.focus({ preventScroll: true });
    window.scrollTo(0, 0);
  }, [view, navigating]);
  useEffect(() => { if (error || imageFailure) errorRegion.current?.focus(); }, [error, imageFailure]);
  const config = run?.configuration || initial.configuration;
  const screen = view === "done" && run ? finalScreen(run) : config.steps.find(s => s.id === view);
  const saved = !!run && run.completed_steps.includes(view) && view !== "done" && !navigating && !imageFailure;
  const commercial = !!screen && ["paywall", "upsell", "final"].includes(screen.type) && (!saved || screen.type === "final");
  const responses = config.steps.filter(s => s.enabled && ["single_choice", "scale"].includes(s.type));
  const confirmed = responses.filter(s => run?.completed_steps.includes(s.id)).length;
  const completed = confirmed + (busy && pending && view !== pending.step && !run?.completed_steps.includes(pending.step) && responses.some(s => s.id === pending.step) ? 1 : 0);
  const progress = responses.length ? Math.round(completed / responses.length * 100) : 0;
  const locked = busy || !!pending || navigating || !!imageFailure || !run;
  const previewing = busy && !!pending && pending.step !== view;
  useEffect(() => {
    warmUpcomingAssets(config, view === "done" && run ? finalScreen(run)?.id || config.entry : view);
    warmFunnelAssets(config, run?.configuration_version || initial.configuration_version);
    // Static artwork is safe to warm; fetching it does not claim screen exposure.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [view, run?.configuration_version]);
  useEffect(() => {
    if (!run || !screen || navigating || !screenAssetsReady(screen)) return;
    let first = 0, second = 0;
    function expose() {
      if (document.visibilityState !== "visible") return;
      first = requestAnimationFrame(() => { second = requestAnimationFrame(() => {
        if (document.visibilityState !== "visible") return;
        observe("step_viewed", screen!.id);
        if (screen!.type === "paywall" && !saved) observe("paywall_viewed_v3", screen!.id);
        if (screen!.type === "final" && run!.commerce.unlocked && run!.result) observe("report_viewed_v3", screen!.id);
      }); });
    }
    expose(); document.addEventListener("visibilitychange", expose);
    return () => { cancelAnimationFrame(first); cancelAnimationFrame(second); document.removeEventListener("visibilitychange", expose); };
    // Capture painted previews now, but deliver only once the server permits the screen.
    // Progress writes take priority over the small, metadata-only exposure outbox.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [view, run?.id, navigating]);
  return <div className={`v3-shell${commercial ? "" : " v3-quiz-shell"}`}>
    {!commercial && <header className="v3-header"><div className="v3-header-row"><a className="v3-brand" href="/" onClick={e => e.preventDefault()} aria-label="BrainSprint"><span className="v3-brand-icon" aria-hidden="true">✳</span>BrainSprint</a><div className="v3-header-tools"><span className="v3-timer" aria-label={`Elapsed time ${elapsed(run?.started_at, now)}`}><span aria-hidden="true">◷</span> {elapsed(run?.started_at, now)}</span><button type="button" className="v3-info-button" aria-label="About this demo and your saved progress" onClick={() => information.current?.showModal()}>ⓘ</button></div></div><div className="v3-progress" role="progressbar" aria-label="Quiz progress" aria-valuemin={0} aria-valuemax={responses.length} aria-valuenow={completed}><span style={{ width: `${progress}%` }} /></div></header>}
    <main ref={headingRegion} className={`${commercial ? "v3-commerce-main" : "v3-main"}${previewing || navigating || imageFailure ? " v3-inflight-preview" : ""}`}>
      {deleted && <p className={`v3-notice${commercial ? "" : " v3-sr-only"}`} role="status">Your saved data was deleted. This is a new anonymous visit.</p>}
      {screen && !screenAssetsReady(screen) ? <section className="v3-question v3-saved" aria-busy="true"><h1>Opening your question…</h1><p role="status">Preparing the illustration.</p></section> : screen ? saved && screen.type !== "final" ? <SavedScreen screen={screen} run={run!} onContinue={() => show(run!.current_step)} /> : commercial && run ? <V3Commerce key={screen.id} screen={screen} run={run} busy={locked} onSubmit={answer => { void save(answer, screen.id); }} /> : <V3Quiz key={screen.id} screen={screen} busy={locked} saving={busy && !previewing} draft={pending?.step === screen.id ? pending.answer : undefined} onSubmit={answer => { void save(answer, screen.id); }} progress={progress} /> : <section className="v3-loading"><h1>Your saved progress is here.</h1><p>Restore your current screen to continue.</p><button className="v3-primary" disabled={busy} onClick={restore}>Restore progress</button></section>}
      {imageFailure && <div className="v3-error" role="alert" tabIndex={-1} ref={errorRegion}><p>{imageFailure.message}</p><div><button className="v3-primary" onClick={() => show(imageFailure.step, true)}>Retry illustration</button><button className="v3-text-button" disabled={busy} onClick={restore}>Restore saved progress</button></div></div>}
      {error && <div className="v3-error" role="alert" tabIndex={-1} ref={errorRegion}><p>{error}</p>{pending && <p>Your selected answer is kept for an identical retry.</p>}<div><button className="v3-primary" disabled={busy} onClick={() => pending ? void save(pending.answer, pending.step) : run ? void restore() : void start()}>{busy ? "Saving…" : pending ? "Retry saving" : "Try again"}</button>{run && <button className="v3-text-button" disabled={busy} onClick={restore}>Restore saved progress</button>}</div></div>}
    </main>
    {!commercial && <dialog ref={information} className="v3-dialog v3-information" aria-labelledby="v3-information-title">
      <h2 id="v3-information-title">About BrainSprint</h2>
      {deleted && <p>Your saved data was deleted. This is a new anonymous visit.</p>}
      {run && <nav className="v3-history" aria-label="Saved progress">{saved ? <button className="v3-text-button" disabled={locked} onClick={() => { information.current?.close(); show(run.current_step); }}>Return to current screen</button> : <span>{confirmed} of {responses.length} responses saved</span>}</nav>}
      <div className="v3-privacy"><Privacy locked={locked} /></div><footer className="v3-footer"><span>BrainSprint © 2026</span><a href="/demo-analytics">Sample analytics ↗</a></footer>
      <button type="button" className="v3-primary" onClick={() => information.current?.close()}>Back to my question</button>
    </dialog>}
  </div>;
}
function SavedScreen({ screen, run, onContinue }: { screen: V3Screen; run: V3Run; onContinue: () => void }) {
  const answer = run.answers[screen.id];
  const label = answer && "choice" in answer ? screen.options?.find(o => o.id === answer.choice)?.label : answer && "email" in answer ? answer.email ? "Email preference saved" : "Skipped" : answer && "decision" in answer ? answer.decision === "accept" ? "Guide added" : "Report only" : "Completed";
  return <section className="v3-question v3-saved"><span className="v3-status-icon" aria-hidden="true">✓</span><p className="v3-eyebrow">Saved response</p><h1>{screen.title}</h1><p>{label}</p><p>Your confirmed answer is saved. Continue from where you left off.</p><div className="v3-actions"><button className="v3-primary" onClick={onContinue}>Continue my progress <span aria-hidden="true">→</span></button></div></section>;
}
