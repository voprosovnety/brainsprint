"use client";

import { useEffect, useState } from "react";
import { DemoAnalytics, formatRate } from "../../lib/demo-analytics";
import { WaitingNotice } from "../../components/waiting-notice";
import "./analytics.css";

const stages = [
  ["funnel_started", "Started", "Opened the challenge"],
  ["quiz_confirmed_v3", "Quiz confirmed", "Confirmed the quiz responses"],
  ["processing_completed_v3", "Report prepared", "Finished report preparation"],
  ["paywall_viewed_v3", "Offer viewed", "Actually saw the simulated report offer"],
  ["checkout_viewed_v3", "Checkout viewed", "Opened the mock checkout"],
  ["mock_unlocked_v3", "Report unlocked", "Confirmed the simulated unlock"],
  ["upsell_decided_v3", "Guide choice saved", "Accepted or declined the guide"],
  ["report_viewed_v3", "Report viewed", "Actually saw the unlocked report"],
  ["funnel_completed_v3", "Journey finished", "Confirmed the final step"],
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
            <p className="analytics-footnote">Bars show observed runs. Conversion uses the intersection with the previous stage and its eligible denominator. Missing screen views are never filled in. Mock unlocks and guide choices are simulated; no purchase or revenue is recorded.</p>
          </section>
          <section className="analytics-timing"><div><p className="analytics-label">Time, with context</p><h2>{data.quiz_elapsed_seconds.mean === null ? "No confirmed quizzes yet" : `${Math.round(data.quiz_elapsed_seconds.mean)} seconds on average`}</h2></div><p>From start to quiz confirmation across {data.quiz_elapsed_seconds.count} confirmed quizzes. Wall-clock time includes pauses and background time; it is not active play time.</p></section>
        </>}
      </>}
      <footer className="analytics-footer"><p>Illustrative sample. No payments, scores, or scientific claims.</p><a href="/">Make a little room for curiosity ↗</a></footer>
    </main>
  </div>;
}
