"use client";
import { useEffect, useRef, useState } from "react";
import { api, Bootstrap, Run } from "@/lib/api";
type Phase = "ready" | "reveal" | "recall";
const preview = [0, 3, 5, 10, 12];
function Arrow() {
  return <span aria-hidden="true">↗</span>;
}
function Grid({
  pattern,
  interactive = false,
  onToggle,
  size = 4,
}: {
  pattern: number[];
  interactive?: boolean;
  onToggle?: (cell: number) => void;
  size?: number;
}) {
  return (
    <div
      className={`memory-grid ${interactive ? "is-interactive" : ""}`}
      style={{ gridTemplateColumns: `repeat(${size}, 1fr)` }}
      role={interactive ? "group" : "img"}
      aria-label={
        interactive
          ? "Select the cells you remember"
          : "Pattern of highlighted blue cells"
      }
    >
      {Array.from({ length: size * size }, (_, cell) =>
        interactive ? (
          <button
            key={cell}
            className={`cell ${pattern.includes(cell) ? "lit" : ""}`}
            aria-label={`Row ${Math.floor(cell / size) + 1}, column ${(cell % size) + 1}`}
            aria-pressed={pattern.includes(cell)}
            onClick={() => onToggle?.(cell)}
          >
            <span aria-hidden="true">{pattern.includes(cell) ? "✦" : ""}</span>
          </button>
        ) : (
          <span
            key={cell}
            className={`cell ${pattern.includes(cell) ? "lit" : ""}`}
          >
            <span aria-hidden="true">{pattern.includes(cell) ? "✦" : ""}</span>
          </span>
        ),
      )}
    </div>
  );
}
export default function Home() {
  const [bootstrap, setBootstrap] = useState<Bootstrap | null>(null);
  const [run, setRun] = useState<Run | null>(null);
  const [phase, setPhase] = useState<Phase>("ready");
  const [remaining, setRemaining] = useState(0);
  const [selected, setSelected] = useState<number[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const heading = useRef<HTMLHeadingElement>(null);
  const roundHeading = useRef<HTMLHeadingElement>(null);
  const [view, setView] = useState<"landing" | "round" | "result">("landing");
  async function load() {
    setError("");
    setBusy(true);
    try {
      const b = await api<Bootstrap>("bootstrap");
      setBootstrap(b);
      setRun(b.run);
      setView(b.run ? (b.run.result ? "result" : "round") : "landing");
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  useEffect(() => {
    void load();
  }, []);
  useEffect(() => {
    if (bootstrap) heading.current?.focus();
  }, [view, bootstrap]);
  useEffect(() => {
    if (phase !== "ready") roundHeading.current?.focus();
  }, [phase]);
  useEffect(() => {
    if (phase !== "reveal") return;
    const deadline =
      Date.now() + (run?.configuration.steps[0].reveal_seconds || 5) * 1000;
    const timer = setInterval(() => {
      const left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
      setRemaining(left);
      if (!left) setPhase("recall");
    }, 100);
    return () => clearInterval(timer);
  }, [phase, run]);
  async function start() {
    setBusy(true);
    setError("");
    try {
      const r = await api<Run>("runs", {});
      setRun(r);
      setView(r.result ? "result" : "round");
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  async function save() {
    if (!run) return;
    setBusy(true);
    setError("");
    try {
      const r = await api<Run>(`runs/${run.id}/steps/memory/complete`, {
        answer: { cells: selected },
      });
      setRun(r);
      setView("result");
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  const config = run?.configuration || bootstrap?.configuration;
  return (
    <div className="site-shell">
      <header className="site-header">
        <a className="brand" href="/" aria-label="BrainSprint home">
          <span className="brand-mark" aria-hidden="true">
            <i />
            <i />
            <i />
            <i />
          </span>
          brain<span>sprint</span>
          <span className="brand-dot">.</span>
        </a>
        <span className="header-note">A little space for your mind</span>
        <span className="demo-tag">The demo edition</span>
      </header>
      <main>
        {!bootstrap ? (
          <section className="loading-panel">
            <div className="tiny-pattern" aria-hidden="true">
              ✦
            </div>
            <h1 ref={heading} tabIndex={-1}>
              A moment to settle in.
            </h1>
            {busy ? (
              <p role="status">Preparing your challenge…</p>
            ) : (
              <>
                <p role="alert">{error}</p>
                <button className="button" onClick={load}>
                  Try again <Arrow />
                </button>
              </>
            )}
          </section>
        ) : (
          <>
            {view === "landing" && (
              <>
                <section className="hero">
                  <div className="hero-copy">
                    <p className="eyebrow">
                      <span /> {config?.landing.eyebrow}
                    </p>
                    <h1 ref={heading} tabIndex={-1}>
                      {config?.landing.title}
                    </h1>
                    <p className="intro">{config?.landing.description}</p>
                    <button className="button" onClick={start} disabled={busy}>
                      {busy
                        ? "Opening your round…"
                        : run?.result
                          ? "View my saved result"
                          : config?.landing.cta}
                      <Arrow />
                    </button>
                    <p className="cta-note">
                      About a minute <span>·</span> No sign-up <span>·</span>{" "}
                      Just curiosity
                    </p>
                  </div>
                  <div className="hero-art">
                    <div className="art-top">
                      <span className="mono">VISUAL MEMORY</span>
                      <span className="art-pill">A preview of your round</span>
                    </div>
                    <Grid pattern={preview} />
                    <div className="art-bottom">
                      <span className="spark">✦</span>
                      <p>
                        Look a little closer.
                        <br />
                        <strong>There’s a pattern waiting.</strong>
                      </p>
                      <span className="orbit" aria-hidden="true">
                        ↗
                      </span>
                    </div>
                  </div>
                </section>
                <section className="how">
                  <div className="how-title">
                    <p className="eyebrow">A change of pace</p>
                    <h2>
                      Less scrolling.
                      <br />
                      More noticing.
                    </h2>
                  </div>
                  <div className="how-step">
                    <span className="step-icon" aria-hidden="true">
                      ◉
                    </span>
                    <h3>Take it in</h3>
                    <p>
                      A few cells light up. Give the pattern a moment of your
                      attention.
                    </p>
                  </div>
                  <div className="how-step">
                    <span className="step-icon" aria-hidden="true">
                      ▦
                    </span>
                    <h3>Bring it back</h3>
                    <p>
                      Tap the cells you remember. There’s no clock on your
                      answer.
                    </p>
                  </div>
                  <div className="how-step">
                    <span className="step-icon" aria-hidden="true">
                      ✦
                    </span>
                    <h3>Stay curious</h3>
                    <p>
                      Explore an illustrative result. This is play, not a
                      measure of ability.
                    </p>
                  </div>
                </section>
              </>
            )}
            {view === "round" && run && (
              <section className="round-layout">
                <aside className="round-copy">
                  <p className="eyebrow">Your quiet minute</p>
                  <p className="round-number">
                    01 <span>/ 01</span>
                  </p>
                  <h1 ref={heading} tabIndex={-1}>
                    {run.configuration.steps[0].title}
                  </h1>
                  <p className="intro">
                    A few blue cells. One small pattern. How much will you bring
                    back?
                  </p>
                  <div className="round-context">
                    <span aria-hidden="true">↺</span>
                    <p>
                      Your round is saved in this browser.
                      <br />
                      If you reload, an unfinished pattern starts again.
                    </p>
                  </div>
                  <p className="no-score">
                    No scores. No pressure. Take your time.
                  </p>
                </aside>
                <div className="challenge-card">
                  <div className="card-heading">
                    <span className="mono">VISUAL MEMORY</span>
                    <span className="art-pill">One round</span>
                  </div>
                  <div className="challenge-instructions" aria-live="polite">
                    <h2 ref={roundHeading} tabIndex={-1}>
                      {phase === "ready"
                        ? bootstrap.run && !bootstrap.run.result
                          ? "Your round is ready again."
                          : "Ready when you are."
                        : phase === "reveal"
                          ? "Let the pattern sink in."
                          : "What stayed with you?"}
                    </h2>
                    <p>
                      {phase === "ready"
                        ? `You’ll have ${run.configuration.steps[0].reveal_seconds} seconds to look. Then tap the cells you remember. Reloading restarts an unfinished pattern.`
                        : phase === "reveal"
                          ? "Notice where the blue cells sit. They’ll hide in a moment."
                          : "Tap to select a cell. Tap again to change your mind."}
                    </p>
                  </div>
                  <Grid
                    size={run.configuration.steps[0].grid_size}
                    pattern={
                      phase === "reveal"
                        ? run.configuration.steps[0].pattern
                        : phase === "recall"
                          ? selected
                          : []
                    }
                    interactive={phase === "recall" && !busy}
                    onToggle={(cell) =>
                      setSelected((old) =>
                        old.includes(cell)
                          ? old.filter((c) => c !== cell)
                          : [...old, cell],
                      )
                    }
                  />
                  <div className="challenge-actions">
                    {phase === "ready" ? (
                      <button
                        className="button"
                        onClick={() => {
                          setRemaining(
                            run.configuration.steps[0].reveal_seconds,
                          );
                          setPhase("reveal");
                        }}
                      >
                        Show the pattern <Arrow />
                      </button>
                    ) : phase === "reveal" ? (
                      <div
                        className="countdown"
                        role="timer"
                        aria-label={`${remaining} seconds remaining`}
                      >
                        <span>{remaining}</span> seconds to look
                      </div>
                    ) : (
                      <>
                        <p className="selection-count" role="status">
                          {selected.length}{" "}
                          {selected.length === 1 ? "cell" : "cells"} selected{" "}
                          <button
                            className="text-button"
                            disabled={busy || !selected.length}
                            onClick={() => setSelected([])}
                          >
                            Clear
                          </button>
                        </p>
                        <button
                          className="button"
                          onClick={save}
                          disabled={busy}
                        >
                          {busy ? "Saving your round…" : "Save my pattern"}
                          <Arrow />
                        </button>
                      </>
                    )}
                  </div>
                </div>
              </section>
            )}
            {view === "result" && run?.result && (
              <section className="result-layout">
                <div className="result-copy">
                  <p className="eyebrow">
                    <span /> Round saved
                  </p>
                  <div className="result-flower" aria-hidden="true">
                    ✦
                  </div>
                  <h1 ref={heading} tabIndex={-1}>
                    {run.configuration.result.title}
                  </h1>
                  <p className="intro">
                    You paused, noticed, and brought a pattern back. Thanks for
                    giving your mind a moment to play.
                  </p>
                  <div className="saved-note">
                    <span aria-hidden="true">✓</span> Your result is saved in
                    this browser.
                  </div>
                  <button
                    className="text-button home-link"
                    onClick={() => {
                      setView("landing");
                      setPhase("ready");
                      setSelected([]);
                    }}
                  >
                    Back to the beginning <span aria-hidden="true">↗</span>
                  </button>
                </div>
                <div className="result-card">
                  <div className="card-heading">
                    <span className="mono">YOUR DEMO SNAPSHOT</span>
                    <span className="art-pill">Illustrative</span>
                  </div>
                  <h2>A glimpse of the experience.</h2>
                  <p className="result-explain">
                    These randomly generated values show how a result could
                    look. They do not measure your abilities.
                  </p>
                  <div className="indicators">
                    {run.result.indicators.map((item, i) => (
                      <div className="indicator" key={item.label}>
                        <div>
                          <span>{item.label}</span>
                          <strong>
                            {item.value}
                            <small>/100</small>
                          </strong>
                        </div>
                        <div className={`indicator-track track-${i}`}>
                          <span style={{ width: `${item.value}%` }} />
                        </div>
                      </div>
                    ))}
                  </div>
                  <div className="result-disclaimer">
                    <span aria-hidden="true">ⓘ</span>
                    <p>
                      {run.result.disclaimer} Not a cognitive assessment, IQ
                      score, or comparison with other people.
                    </p>
                  </div>
                  <p className="result-end">
                    That’s the memory preview. Thanks for playing.
                  </p>
                </div>
              </section>
            )}
            {error && (
              <div className="error-message" role="alert">
                <strong>Let’s try that again.</strong> {error}
              </div>
            )}
          </>
        )}
      </main>
      <footer className="site-footer">
        <span>Small challenge. Curious mind.</span>
        <p>For entertainment only. Not a cognitive assessment.</p>
        <span className="footer-word">BrainSprint © 2026</span>
      </footer>
    </div>
  );
}
