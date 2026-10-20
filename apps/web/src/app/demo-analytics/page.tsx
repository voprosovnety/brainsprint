"use client";

import { useEffect, useState } from "react";
import { DemoAnalytics, formatRate } from "../../lib/demo-analytics";
import { WaitingNotice } from "../../components/waiting-notice";
import "./analytics.css";

const stages = [
  ["funnel_started", "Started", "Opened the challenge"],
  ["quiz_completed", "Quiz completed", "Reached an illustrative result"],
  ["result_viewed", "Result viewed", "Actually saw the snapshot"],
  ["paywall_reached", "Boundary viewed", "Saw the no-payment finish screen"],
  ["demo_completed", "Demo finished", "Confirmed the final step"],
];
const date = (value: string) => new Date(value).toLocaleDateString("en-GB", { day: "numeric", month: "short", timeZone: "UTC" });

export default function DemoAnalyticsPage() {
  const [data, setData] = useState<DemoAnalytics | null>(null);
  const [status, setStatus] = useState<"loading" | "ready" | "error">("loading");
  const [attempt, setAttempt] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 100000);
    let active = true;
    setStatus("loading");
    fetch("/api/v1/demo-analytics", { cache: "no-store", signal: controller.signal })
      .then(async response => { if (!response.ok) throw new Error(); return response.json() as Promise<DemoAnalytics>; })
      .then(summary => { if (summary.dataset !== "synthetic") throw new Error(); if (active) { setData(summary); setStatus("ready"); } })
      .catch(() => { if (active) setStatus("error"); })
      .finally(() => clearTimeout(timeout));
    return () => { active = false; clearTimeout(timeout); controller.abort(); };
  }, [attempt]);

  return <div className="analytics-page">
    <header className="analytics-header"><a href="/" className="analytics-brand">brain<span>sprint.</span></a><a href="/">← Back to the challenge</a></header>
    <main className="analytics-main">
      <div className="analytics-heading"><p className="analytics-kicker">Demo analytics <span>● Synthetic data</span></p><h1>A small look<br />at the journey.</h1><p>From a moment of curiosity to the final screen. Explore an illustrative sample of the BrainSprint funnel, built from synthetic scenarios.</p></div>
      <aside className="analytics-note"><strong>Made-up journeys. Shared definitions.</strong><p>These are seeded scenarios and local demo activity, not real visitors or evidence of cognitive ability. The same metric definitions power the private dashboard.</p></aside>
      {status === "loading" && <section className="analytics-state" role="status" aria-live="polite"><span className="analytics-loader" aria-hidden="true" /><h2>Gathering the sample…</h2><p>Loading the last seven days of synthetic journeys.</p></section>}
      <WaitingNotice active={status === "loading"} />
      {status === "error" && <section className="analytics-state" role="alert"><h2>The sample could not load.</h2><p>Try again in a moment. You can still return to the challenge.</p><button className="button" onClick={() => setAttempt(n => n + 1)}>Try again</button></section>}
      {status === "ready" && data && <>
        <div className="analytics-period"><span>{date(data.cohort.from)} – {date(data.cohort.to_exclusive)} · UTC</span><span>7-day cohort · {data.cohort.observation_hours}-hour observation window</span></div>
        {data.totals.runs === 0 ? <section className="analytics-state" role="status"><h2>No synthetic journeys in this window.</h2><p>The sample covers the last seven days. Return after the demo sample is refreshed.</p><button className="text-button" onClick={() => setAttempt(n => n + 1)}>Refresh sample ↗</button></section> : <>
          <section className="analytics-journey" aria-labelledby="journey-heading"><div className="analytics-section-head"><div><p className="analytics-label">The run cohort</p><h2 id="journey-heading">Every stage tells a little more.</h2></div><p>{data.totals.runs} synthetic runs</p></div>
            <ol className="analytics-stages">{stages.map(([key, title, description]) => {
              const conversion = data.stage_conversions[key];
              return <li key={key}><div className="analytics-stage-copy"><span>{title}</span><small>{description}</small></div><div className="analytics-track"><div style={{ width: `${Math.max(0, Math.min(100, data.totals[key] / data.totals.runs * 100))}%` }} /></div><strong>{data.totals[key]}</strong><div className="analytics-stage-rate">{conversion ? <><b>{formatRate(conversion)}</b><small>{conversion.numerator} / {conversion.denominator} eligible at previous stage</small></> : <small>Unique observed starts</small>}</div></li>;
            })}</ol>
            <p className="analytics-footnote">Bars show observed runs. Conversion uses the intersection with the previous stage and its eligible denominator. Missing screen views are never filled in. The boundary has no payment or checkout.</p>
          </section>
          {data.v3 && <section className="analytics-journey" aria-labelledby="v3-heading"><div className="analytics-section-head"><div><p className="analytics-label">V3 journeys · separate cohort</p><h2 id="v3-heading">From first word to a new perspective.</h2></div><p>{data.v3.runs} synthetic runs</p></div><ol className="analytics-stages">{Object.entries(data.v3.totals).map(([key, count]) => { const rate = data.v3.stage_conversions[key]; return <li key={key}><div className="analytics-stage-copy"><span>{key.replace(/_v3$/, "").replaceAll("_", " ")}</span><small>Observed unique journeys</small></div><div className="analytics-track"><div style={{width: `${data.v3.runs ? Math.min(100, count / data.v3.runs * 100) : 0}%`}} /></div><strong>{count}</strong><div className="analytics-stage-rate">{rate && <><b>{formatRate(rate)}</b><small>{rate.numerator} / {rate.denominator} eligible at previous stage</small></>}</div></li>; })}</ol><p className="analytics-footnote">Mock unlocks and guide choices are demonstration states. No purchase or revenue is recorded. Historical stages and the landing experiment below retain their original meanings.</p></section>}
          <section className="analytics-experiment" aria-labelledby="experiment-heading"><div className="analytics-section-head"><div><p className="analytics-label">The landing experiment</p><h2 id="experiment-heading">Two invitations. One challenge.</h2></div><span className="analytics-evidence">Insufficient evidence</span></div><p className="analytics-experiment-intro">A keeps the Memory preview. B introduces all three categories. Rates below use visitors whose full {data.cohort.observation_hours}-hour window has elapsed after their first landing view.</p>
            <div className="analytics-variants">{(["A", "B"] as const).map(variant => { const v = data.experiment.variants[variant]; return <article key={variant}><div className="analytics-variant-title"><span>{variant}</span><h3>{variant === "A" ? "A curious detour" : "Find my rhythm"}</h3></div><p className="analytics-variant-counts">{v.exposed} exposed · {v.eligible_exposed} eligible · {v.pending_observation} pending</p><dl>{[["Started", v.start_conversion], ["Completed quiz", v.quiz_conversion], ["Viewed boundary", v.boundary_conversion]].map(([label, rate]) => { const r = rate as typeof v.start_conversion; return <div key={label as string}><dt>{label as string}</dt><dd><strong>{formatRate(r)}</strong><small>{r.numerator} / {r.denominator} eligible visitors</small></dd></div>; })}</dl></article>; })}</div>
            <p className="analytics-footnote">A separate first-exposure cohort: these denominators differ from the run cohort above. Pending visitors are waiting for follow-up, not counted as failures. No statistical winner is declared.</p>
          </section>
          <section className="analytics-timing"><div><p className="analytics-label">Time, with context</p><h2>{data.quiz_elapsed_seconds.mean === null ? "No completed quizzes yet" : `${Math.round(data.quiz_elapsed_seconds.mean)} seconds on average`}</h2></div><p>From start to quiz completion across {data.quiz_elapsed_seconds.count} observed quizzes. Wall-clock time includes pauses and background time; it is not active play time.</p></section>
        </>}
      </>}
      <footer className="analytics-footer"><p>Illustrative sample. No payments, scores, or scientific claims.</p><a href="/">Make a little room for curiosity ↗</a></footer>
    </main>
  </div>;
}
