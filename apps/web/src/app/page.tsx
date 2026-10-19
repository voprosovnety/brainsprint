"use client";
import { useEffect, useRef, useState } from "react";
import { api, presentConfiguration, type Answer, type Bootstrap, type DisplayConfiguration, type Run, type Screen } from "@/lib/api";
import { Arrow, Grid, Logic, Memory, Pattern } from "@/components/challenges";

const categories = [
  { icon: "▦", title: "Remember", description: "Watch a pattern. Bring it back.", type: "visual_memory" },
  { icon: "◆", title: "Notice", description: "Find the rhythm. See what follows.", type: "pattern" },
  { icon: "≈", title: "Untangle", description: "Follow the clues. Make a little order.", type: "logic" },
];
function allowed(run: Run | null, screen: string) {
  return screen === "landing" || !!run && (screen === run.current_step || run.completed_steps.includes(screen));
}
function canonical(run: Run | null) { return run?.current_step || "landing"; }
function requested(run: Run | null) {
  const screen = new URLSearchParams(window.location.search).get("screen");
  return screen && allowed(run, screen) ? screen : canonical(run);
}
function orderedChallenges(config: DisplayConfiguration | undefined) {
  if (!config) return [];
  if (!config.schema_version) return config.steps;
  const ordered: Screen[] = [];
  const seen = new Set<string>();
  let id = config.entry;
  while (id && id !== "done" && !seen.has(id)) {
    seen.add(id);
    const screen = config.steps.find(s => s.id === id);
    if (!screen) break;
    if (screen.enabled && ["visual_memory", "pattern", "logic"].includes(screen.type)) ordered.push(screen);
    id = screen.next;
  }
  return ordered;
}
function CategoryList() {
  return <div className="category-list">{categories.map((c, i) => <div key={c.type}><span className="category-symbol" aria-hidden="true">{c.icon}</span><div><p className="mono">{String(i + 1).padStart(2, "0")}</p><h3>{c.title}</h3><p>{c.description}</p></div></div>)}</div>;
}
function SavedAnswer({ screen, answer }: { screen: Screen; answer: Answer }) {
  return <div className="saved-answer"><p className="eyebrow">✓ Answer saved</p><h2>You have already visited this step.</h2><p>This is your saved answer. Continue whenever you are ready.</p>
    {"cells" in answer && "grid_size" in screen && <Grid size={screen.grid_size} pattern={answer.cells} />}
    {"choice" in answer && screen.type === "pattern" && <div className="saved-choice">{screen.options.find(o => o.id === answer.choice)?.symbol}<small>{screen.options.find(o => o.id === answer.choice)?.label}</small></div>}
    {"order" in answer && screen.type === "logic" && <div className="saved-order">{answer.order.map(id => <span key={id}>{screen.cards.find(c => c.id === id)?.symbol}<small>{screen.cards.find(c => c.id === id)?.label}</small></span>)}</div>}
  </div>;
}
export default function Home() {
  const [bootstrap, setBootstrap] = useState<Bootstrap | null>(null);
  const [run, setRun] = useState<Run | null>(null);
  const [view, setView] = useState("landing");
  const [busy, setBusy] = useState(true);
  const [error, setError] = useState("");
  const [restored, setRestored] = useState(false);
  const [pending, setPending] = useState<{ step: string; answer: Answer } | null>(null);
  const runRef = useRef<Run | null>(null);
  const viewRef = useRef("landing");
  const writing = useRef(false);
  const heading = useRef<HTMLHeadingElement>(null);
  const errorPanel = useRef<HTMLDivElement>(null);
  function show(screen: string, replace = false) {
    viewRef.current = screen;
    setView(screen);
    const url = screen === "landing" ? "/?screen=landing" : `/?screen=${encodeURIComponent(screen)}`;
    if (replace) window.history.replaceState(null, "", url);
    else window.history.pushState(null, "", url);
  }
  function accept(r: Run | null) { runRef.current = r; setRun(r); }
  async function load(initial = false) {
    setBusy(true); setError("");
    try {
      const b = await api<Bootstrap>("bootstrap");
      setBootstrap(b); accept(b.run); setPending(null); setRestored(!!b.run);
      show(initial ? requested(b.run) : canonical(b.run), true);
    } catch (e) { setError((e as Error).message || "The demo could not open. Please try again."); }
    finally { setBusy(false); }
  }
  useEffect(() => {
    void load(true);
    function pop() {
      const r = runRef.current;
      setRestored(!!r);
      show(requested(r), true);
    }
    window.addEventListener("popstate", pop);
    return () => window.removeEventListener("popstate", pop);
    // Bootstrap once; confirmed state is kept in runRef for history navigation.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => { if (bootstrap) { heading.current?.focus(); window.scrollTo({ top: 0 }); } }, [view, bootstrap]);
  useEffect(() => { if (error) errorPanel.current?.focus(); }, [error]);
  async function start() {
    if (run) { setRestored(true); show(canonical(run)); return; }
    if (writing.current) return;
    writing.current = true; setBusy(true); setError("");
    try { const r = await api<Run>("runs", {}); accept(r); show(canonical(r)); }
    catch (e) { setError((e as Error).message); }
    finally { writing.current = false; setBusy(false); }
  }
  async function save(answer: Answer, step = viewRef.current) {
    if (!runRef.current || writing.current) return;
    const id = runRef.current.id;
    writing.current = true; setBusy(true); setError(""); setPending({ step, answer });
    try {
      const r = await api<Run>(`runs/${id}/steps/${encodeURIComponent(step)}/complete`, { answer });
      accept(r); setPending(null); setRestored(false);
      if (viewRef.current === step) show(canonical(r));
    } catch (e) { setError((e as Error).message || "Your answer could not be saved. Try again when you are connected."); }
    finally { writing.current = false; setBusy(false); }
  }
  const rawConfig = run?.configuration || bootstrap?.configuration;
  const config = rawConfig ? presentConfiguration(rawConfig) : undefined;
  const legacy = !!run && !run.configuration.schema_version;
  const screen = config?.steps.find(s => s.id === view) || (legacy && view === "result" ? { id: "result", type: "result", title: config?.result.title, description: "A little curiosity goes a long way.", variant: "standard", enabled: true, next: "done" } as Screen : null);
  const saved = !!run?.completed_steps.includes(view);
  const challenges = orderedChallenges(config);
  const challengeIndex = challenges.findIndex(s => s.id === view);
  const category = categories.find(c => c.type === screen?.type);
  const locked = busy || !!pending;
  const result = run?.result;
  const currentTitle = config?.steps.find(s => s.id === run?.current_step)?.title;
  return <div className="site-shell">
    <header className="site-header"><a className="brand" href="/?screen=landing" aria-label="BrainSprint home" onClick={e => { e.preventDefault(); show("landing"); }}><span className="brand-mark" aria-hidden="true"><i /><i /><i /><i /></span>brain<span>sprint</span><span className="brand-dot">.</span></a><span className="header-note">A little space for your mind</span><span className="demo-tag">The demo edition</span></header>
    <main>
      {!bootstrap ? <section className="loading-panel"><div className="tiny-pattern" aria-hidden="true">✦</div><h1 ref={heading} tabIndex={-1}>A moment to settle in.</h1>{busy ? <p role="status">Preparing your challenge…</p> : <><p role="alert">{error}</p><button className="button" onClick={() => load(true)}>Try again <Arrow /></button></>}</section> : <>
        {view === "landing" && config && <>
          <section className="hero"><div className="hero-copy"><p className="eyebrow"><span />{config.landing.eyebrow}</p><h1 ref={heading} tabIndex={-1}>{config.landing.title}</h1><p className="intro">{config.landing.description}</p><button className="button" disabled={busy} onClick={start}>{busy ? "Opening your challenge…" : run ? run.demo_completed ? "Revisit the demo" : result && legacy ? "View my saved result" : "Continue my demo" : config.landing.cta}<Arrow /></button><p className="cta-note">{legacy ? "A memory preview" : "About 3–4 minutes"}<span>·</span>No sign-up<span>·</span>Just curiosity</p></div>
          <div className="hero-art"><div className="art-top"><span className="mono">A LITTLE PREVIEW</span><span className="art-pill">Look. Remember. Play.</span></div><Grid pattern={[0, 3, 5, 10, 12]} /><div className="art-bottom"><span className="spark" aria-hidden="true">✦</span><p>Look a little closer.<br /><strong>There’s a pattern waiting.</strong></p><span className="orbit" aria-hidden="true">↗</span></div></div></section>
          <section className="how"><div className="how-title"><p className="eyebrow">A change of pace</p><h2>Less scrolling.<br />More noticing.</h2></div>{categories.map(c => <div className="how-step" key={c.type}><span className="step-icon" aria-hidden="true">{c.icon}</span><h3>{c.title}</h3><p>{c.description}</p></div>)}</section>
        </>}
        {screen && !["result", "boundary"].includes(screen.type) && <section className={`round-layout variant-${screen.variant || "standard"}`}>
          <div className="round-copy"><p className="eyebrow">{screen.type === "onboarding" ? "Before we begin" : screen.type === "tutorial" || screen.type === "explanation" ? "A practice, not a test" : category ? `${category.title} · ${category.type.replace("visual_", "").replaceAll("_", " ")}` : "Your challenge"}</p>
          {challengeIndex >= 0 && <><div className="round-number">{String(challengeIndex + 1).padStart(2, "0")}<span> / {String(challenges.length).padStart(2, "0")}</span></div><div className="progress-dots" aria-label={`Challenge ${challengeIndex + 1} of ${challenges.length}`}>{challenges.map((s, i) => <span key={s.id} className={i <= challengeIndex ? "active" : ""} />)}</div></>}
          <h1 ref={heading} tabIndex={-1}>{screen.title}</h1>{screen.type !== "explanation" && <p className="intro">{screen.description}</p>}<div className="round-context"><span aria-hidden="true">✦</span><p>Take your time. There is no score to chase.<br />A change of pace, just for you.</p></div><p className="no-score">For entertainment. Never an assessment.</p></div>
          <div className="challenge-card"><div className="card-heading"><span className="mono">{screen.type === "onboarding" ? "YOUR LITTLE DETOUR" : screen.type === "explanation" ? "A CLOSER LOOK" : screen.type === "tutorial" ? "MEMORY PRACTICE" : screen.type.replaceAll("_", " ").toUpperCase()}</span><span className="art-pill">{saved ? "Saved" : challengeIndex >= 0 ? `${challengeIndex + 1} of ${challenges.length}` : "At your own pace"}</span></div>
            {saved && run ? <><SavedAnswer screen={screen} answer={run.answers[view]} /><div className="challenge-actions"><button className="button" onClick={() => show(canonical(run))}>Return to my progress <Arrow /></button></div></> : <>
              {screen.type === "onboarding" && <><h2 className="onboarding-title">Three ways to get curious.</h2><CategoryList /><p className="onboarding-note">A short practice comes first. The result at the end contains illustrative demo values, independent of your answers.</p><div className="challenge-actions"><button className="button" disabled={locked} onClick={() => save({ acknowledged: true })}>{busy ? "Saving…" : "Let’s find our rhythm"}<Arrow /></button></div></>}
              {(screen.type === "visual_memory" || screen.type === "tutorial") && <Memory key={screen.id} screen={screen} restored={restored} locked={locked} saving={busy} save={save} />}
              {screen.type === "explanation" && <><div className="challenge-instructions"><h2>A closer look at the pattern.</h2><p>{screen.description}</p></div><Grid size={screen.grid_size} pattern={screen.pattern} /><div className="challenge-actions"><button className="button" disabled={locked} onClick={() => save({ acknowledged: true })}>{busy ? "Saving…" : "I’m ready for the rounds"}<Arrow /></button></div></>}
              {screen.type === "pattern" && <Pattern key={screen.id} screen={screen} locked={locked} saving={busy} save={save} />}
              {screen.type === "logic" && <Logic key={screen.id} screen={screen} locked={locked} saving={busy} save={save} />}
            </>}
          </div>
        </section>}
        {screen?.type === "result" && result && <section className="result-layout"><div className="result-copy"><div className="result-flower" aria-hidden="true">✦</div><p className="eyebrow">{legacy ? "Memory preview complete" : "Your challenges, complete"}</p><h1 ref={heading} tabIndex={-1}>{screen.title}</h1><p className="intro">{screen.description}</p><p className="saved-note"><span>✓</span>Your snapshot is saved in this browser.</p></div><div className="result-card"><div className="card-heading"><span className="mono">THE DEMO SNAPSHOT</span><span className="art-pill">Illustrative only</span></div><h2>A playful illustration.</h2><p className="result-explain">These values show how a snapshot can look. They do not measure your performance.</p><div className="indicators">{result.indicators.map((indicator, i) => <div className="indicator" key={indicator.label}><div><span>{indicator.label}</span><strong>{indicator.value}<small>/100</small></strong></div><div className={`indicator-track track-${i}`}><span style={{ width: `${indicator.value}%` }} /></div></div>)}</div><div className="result-disclaimer"><span aria-hidden="true">ⓘ</span><p>{result.disclaimer}</p></div>{legacy ? <p className="result-end">Your saved Memory preview ends here.</p> : <div className="challenge-actions"><button className="button" disabled={locked} onClick={() => saved ? show(canonical(run)) : save({ acknowledged: true })}>{busy ? "Saving…" : saved ? "Return to my progress" : "One last little moment"}<Arrow /></button></div>}</div></section>}
        {(screen?.type === "boundary" || view === "done") && run && <section className="boundary-layout"><div className="boundary-art" aria-hidden="true"><div className="boundary-orbit" /><span className="finish-tile tile-memory">▦</span><span className="finish-tile tile-pattern">◆</span><span className="finish-tile tile-logic">≈</span><span className="finish-spark">✦</span><span className="mono">REMEMBER · NOTICE · UNTANGLE</span></div><div className="boundary-copy"><p className="eyebrow">{run.demo_completed ? "Your demo is complete" : "The end of a little detour"}</p><h1 ref={heading} tabIndex={-1}>{view === "done" ? "Keep a little\ncuriosity with you." : screen?.title}</h1><p className="intro">{view === "done" ? "Thanks for making a little space for your mind. Your demo and illustrative snapshot are saved in this browser." : screen?.description}</p><div className="boundary-note"><span aria-hidden="true">✦</span><p>This is the full BrainSprint demo.<br />A moment of play, with nothing to unlock.</p></div>{view !== "done" && !saved && <button className="button" disabled={locked} onClick={() => save({ acknowledged: true })}>{busy ? "Finishing your demo…" : "Finish demo"}<Arrow /></button>}{view === "done" || saved ? <button className="button" onClick={() => show(config?.steps.find(s => s.type === "result")?.id || "result")}>Revisit my snapshot <Arrow /></button> : null}<p className="boundary-caption">{run.demo_completed ? "✓ Finished and saved" : "No sign-up. No payment. Just a fresh perspective."}</p></div></section>}
        {error && <div ref={errorPanel} tabIndex={-1} className="error-message" role="alert"><p>{error}</p>{pending && <p>Your last answer is kept here for retry. Your confirmed steps remain saved.</p>}<div className="error-actions">{pending ? <button className="text-button" disabled={busy} onClick={() => save(pending.answer, pending.step)}>Retry saving</button> : <button className="text-button" disabled={busy} onClick={() => load()}>Try again</button>}<button className="text-button" disabled={busy} onClick={() => load()}>Restore saved progress</button></div></div>}
        {run && view !== "landing" && !error && <nav className="progress-nav" aria-label="Demo navigation"><button className="text-button" disabled={busy} onClick={() => show("landing")}>Back to the beginning</button>{view !== run.current_step && <button className="text-button" disabled={busy} onClick={() => show(canonical(run))}>{run.demo_completed ? "Return to the finish" : `Continue: ${currentTitle || "my demo"}`}</button>}</nav>}
      </>}
    </main>
    <footer className="site-footer"><span>A little play. A little perspective.</span><p>For entertainment only. Not a cognitive assessment.</p><span className="footer-word">BrainSprint © 2026</span></footer>
  </div>;
}
