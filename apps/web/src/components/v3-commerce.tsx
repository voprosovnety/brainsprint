"use client";

import { useEffect, useRef, useState } from "react";
import { recordView } from "@/lib/analytics";
import type { V3Answer, V3Run, V3Screen } from "@/lib/v3";
import { Privacy } from "./privacy";

type Props = { screen: V3Screen; run: V3Run; busy: boolean; onSubmit: (answer: V3Answer) => void };
type Modal = "checkout" | "information" | null;
const money = (cents: number) => `$${(cents / 100).toFixed(2)}`;
const benefits = [
  ["Your complete thinking profile", "A readable guide to the different ways we approach a challenge."],
  ["A fresh perspective on strengths", "Explore attention, memory, reasoning and the connections between them."],
  ["Patterns worth noticing", "Put language to everyday habits and see familiar situations differently."],
  ["Small ideas for your next day", "Simple, practical prompts you can try at your own pace."],
];
const reportQuestions = [
  ["What is inside the report?", "A complete sample thinking profile with sections on attention, memory, reasoning and everyday practice. You can read every section here after unlocking it."],
  ["Do I need an account?", "No. Your progress and report stay with the anonymous session in this browser. There is no account to create or app to install."],
  ["How does the introductory offer work?", "The displayed offer is $0.99 for three days, with $39.99 every four weeks shown as the follow-on terms. This is a simulated offer: no payment, renewal or subscription is created."],
  ["What happens when the timer ends?", "The introductory countdown ends, but your saved answers and the $0.99 demonstration remain available. You can continue whenever you are ready."],
  ["Is this a cognitive assessment?", "No. The report is illustrative sample content, independent of answer correctness. It does not measure intelligence, health or cognitive ability."],
];
const guideChapters = [
  ["Find a quieter starting point", "Create a little space between a distraction and your next choice."],
  ["Give memory something to hold", "Turn a fleeting detail into an image, connection or useful cue."],
  ["Look at the pattern again", "Ask a different question when your first approach feels stuck."],
  ["Make a small habit last", "Choose a practice that fits a real day, then notice what happens."],
];

function Check({ children }: { children: React.ReactNode }) {
  return <li><span className="vc-check" aria-hidden="true">✓</span><span>{children}</span></li>;
}
function Faq({ items }: { items: string[][] }) {
  return <div className="vc-faq">{items.map(([question, answer]) => <details key={question}><summary>{question}<span aria-hidden="true">+</span></summary><p>{answer}</p></details>)}</div>;
}
function Quote({ quote, name, note }: { quote: string; name: string; note: string }) {
  return <figure className="vc-quote"><div className="vc-stars" aria-label="Five stars">★★★★★</div><blockquote>“{quote}”</blockquote><figcaption><span className="vc-avatar" aria-hidden="true">{name.charAt(0)}</span><span>{name}<small>{note}</small></span><span className="vc-quote-mark" aria-hidden="true">”</span></figcaption></figure>;
}

export function V3Commerce({ screen, run, busy, onSubmit }: Props) {
  const offer = run.configuration.offer;
  const dialogRef = useRef<HTMLDialogElement>(null);
  const checkoutSeen = useRef(false);
  const [modal, setModal] = useState<Modal>(null);
  const [remaining, setRemaining] = useState<number | null>(null);
  const isPaywall = screen.type === "paywall";
  const isUpsell = screen.type === "upsell";
  const final = screen.type === "final";
  const hasGuide = run.commerce.upsell === "accept";
  const savings = money(offer.comparison_cents - offer.intro_cents);

  useEffect(() => {
    if (!isPaywall || !run.commerce.deadline) return;
    const deadline = new Date(run.commerce.deadline).getTime();
    if (!Number.isFinite(deadline)) return;
    const tick = () => setRemaining(Math.max(0, Math.ceil((deadline - Date.now()) / 1000)));
    tick();
    const interval = window.setInterval(tick, 1000);
    return () => window.clearInterval(interval);
  }, [isPaywall, run.commerce.deadline]);

  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    if (modal) {
      if (!dialog.open) dialog.showModal();
      dialog.querySelector<HTMLButtonElement>(".vc-dialog-close")?.focus({ preventScroll: true });
    } else if (dialog.open) dialog.close();
    let frame = 0;
    const exposeCheckout = () => {
      if (modal !== "checkout" || document.visibilityState !== "visible" || checkoutSeen.current) return;
      // Wait for the open dialog to reach a visible frame before recording exposure.
      frame = window.requestAnimationFrame(() => {
        frame = window.requestAnimationFrame(() => {
          if (!dialog.open || document.visibilityState !== "visible" || checkoutSeen.current) return;
          checkoutSeen.current = true;
          void recordView({ name: "checkout_viewed_v3", run_id: run.id, step_id: screen.id });
        });
      });
    };
    exposeCheckout();
    document.addEventListener("visibilitychange", exposeCheckout);
    return () => {
      window.cancelAnimationFrame(frame);
      document.removeEventListener("visibilitychange", exposeCheckout);
    };
  }, [modal, run.id, screen.id]);

  const timer = remaining === null ? "–:––" : `${String(Math.floor(remaining / 60)).padStart(2, "0")}:${String(remaining % 60).padStart(2, "0")}`;
  const unlock = () => setModal("checkout");
  const submitUnlock = () => { setModal(null); onSubmit({ offer_id: screen.offer_id || offer.id, acknowledged: true }); };
  const price = <><span className="vc-old-price">{money(offer.comparison_cents)}</span><strong>{money(offer.intro_cents)}</strong></>;
  const cta = (label = "Unlock my report") => <button className="vc-button" disabled={busy} onClick={unlock}>{busy ? "Saving your unlock…" : label}<span aria-hidden="true">→</span></button>;
  const footer = <footer className="vc-footer"><a className="vc-brand" href="#vc-top" aria-label="BrainSprint, back to top"><span aria-hidden="true">✳</span> BrainSprint</a><p>A little space for a curious mind.</p><button className="vc-text-button" onClick={() => setModal("information")}>Information &amp; offer terms</button><Privacy locked={busy} /><small>© {new Date().getFullYear()} BrainSprint</small></footer>;

  return <div className={`v3-commerce ${final ? "vc-final" : ""}`} id="vc-top">
    <header className="vc-header"><a className="vc-brand" href="#vc-top"><span aria-hidden="true">✳</span> BrainSprint</a><button className="vc-info-button" aria-label="About your report and offer" onClick={() => setModal("information")}>i</button></header>

    {isPaywall && <>
      <div className="vc-community-strip"><span className="vc-mini-avatars" aria-hidden="true"><i>A</i><i>J</i><i>M</i></span><span>A place for curious minds</span><span className="vc-stars" aria-label="Community stories below">★★★★★</span></div>
      <div className="vc-sticky-offer"><div><span className="vc-sticky-label">{remaining === 0 ? "Your report is still waiting" : "Your introductory offer"}</span><div className="vc-sticky-price">{price}<span className="vc-timer" aria-label={remaining === 0 ? "Introductory countdown ended" : "Time remaining on introductory countdown"}>{timer}</span></div></div><button className="vc-button" disabled={busy} onClick={unlock}>{busy ? "Saving…" : "Unlock report"}<span aria-hidden="true">→</span></button></div>
      <div className="vc-content">
        <section className="vc-hero vc-section"><div className="vc-pill"><span aria-hidden="true">✓</span> Your quiz is complete</div><h1>{screen.title}</h1><p className="vc-lead">{screen.description}</p><img className="vc-hero-art" src="/v3/report-hero.svg" width="640" height="440" alt="An open thinking profile with a lavender path connecting memory, focus and patterns." /><div className="vc-hero-caption"><span aria-hidden="true">✦</span> A new perspective, one page at a time.</div></section>
        <section className="vc-offer-card vc-section" aria-labelledby="vc-offer-heading"><div className="vc-promo"><span aria-hidden="true">✓</span> Introductory promotion applied <strong>Save {savings}</strong></div><div className="vc-offer-body"><span className="vc-eyebrow">Your next step</span><h2 id="vc-offer-heading">Meet your thinking profile.</h2><p>Make space for a closer look at the habits behind everyday thinking.</p><div className="vc-big-price">{price}<span>for your first {offer.intro_days} days</span></div>{cta()}<div className="vc-checkout-hint"><span aria-hidden="true">▣</span> Private access in this browser</div><ul className="vc-benefits">{benefits.map(([title, description]) => <Check key={title}><strong>{title}</strong><p>{description}</p></Check>)}</ul><p className="vc-terms">Introductory offer: {money(offer.intro_cents)} for {offer.intro_days} days, then {money(offer.renewal_cents)} every {offer.renewal_weeks} weeks. These are displayed offer terms; this experience creates no charge or subscription. <button className="vc-inline-link" onClick={() => setModal("information")}>View details</button></p>{remaining === 0 && <p className="vc-expired" role="status">The countdown has ended. Your introductory demonstration remains available.</p>}</div></section>
        <section className="vc-section vc-method"><span className="vc-eyebrow">Built around everyday thinking</span><h2>A little understanding.<br />A useful place to start.</h2><p>Memory, attention and reasoning show up in ordinary moments. Your report brings them into focus with clear explanations and practical prompts.</p><div className="vc-method-grid"><article><span aria-hidden="true">◉</span><h3>Observe</h3><p>Notice how you approach a new challenge.</p></article><article><span aria-hidden="true">⌘</span><h3>Connect</h3><p>Explore different ways to frame the same situation.</p></article><article><span aria-hidden="true">✦</span><h3>Try</h3><p>Take one small idea into your everyday routine.</p></article></div><p className="vc-method-note">Designed for reflection and curiosity.</p></section>
        <section className="vc-section vc-comparison"><span className="vc-eyebrow">From curiosity to a next step</span><h2>Find a little more clarity.</h2><div className="vc-comparison-grid"><article><span className="vc-comparison-label">Where you might be</span><div className="vc-tangle" aria-hidden="true">〰<br />〰〰<br />〰</div><h3>“Where do I begin?”</h3><p>A handful of questions, with no clear starting point.</p></article><article><span className="vc-comparison-label">A direction to explore</span><div className="vc-path" aria-hidden="true">·<br />│<br />✦</div><h3>“I can try one thing.”</h3><p>A readable profile and a practical idea for today.</p></article></div>{cta("Explore my report")}</section>
        <section className="vc-section vc-preview"><span className="vc-eyebrow">A look inside</span><h2>{run.teaser?.title || "Your thinking profile"}</h2><p>{run.teaser?.summary || "Explore your sample profile, from the bigger picture to everyday practice."}</p><div className="vc-preview-art"><img src="/v3/report-preview.svg" width="600" height="620" alt="Illustration of a report layout with its page details softly obscured." /><div className="vc-preview-lock"><span aria-hidden="true">▣</span><strong>Your full report is ready</strong><p>Unlock to read every section.</p></div></div><ul className="vc-preview-contents">{(run.teaser?.sections || ["Attention & focus", "Memory & connections", "Reasoning & patterns", "Everyday practice"]).map(title => <Check key={title}>{title}</Check>)}</ul>{cta()}</section>
        <section className="vc-section"><span className="vc-eyebrow">Different minds, shared curiosity</span><h2>Small ideas can open<br />a new conversation.</h2><p className="vc-section-intro">A few stories from our sample community.</p><div className="vc-quotes"><Quote quote="I liked having one small prompt to think about, instead of a long list of things to change." name="Alex" note="On making a start" /><Quote quote="The memory ideas gave me a new way to look at something I do every day." name="Jordan" note="On everyday connections" /><Quote quote="A calm space to pause and look at a familiar problem from a different angle." name="Morgan" note="On finding perspective" /></div></section>
        <section className="vc-section vc-reassurance"><span className="vc-reassurance-icon" aria-hidden="true">♡</span><h2>Go at your own pace.</h2><p>There is no perfect way to read your report. Start with the section that catches your attention and come back when you want another idea.</p>{cta("Open my thinking profile")}<span className="vc-reassurance-note">One browser. Your saved progress. Room to explore.</span></section>
        <section className="vc-section"><span className="vc-eyebrow">A few helpful answers</span><h2>Before you begin.</h2><Faq items={reportQuestions} /></section>
      </div>
    </>}

    {isUpsell && <div className="vc-content">
      <div className="vc-saved-strip"><span aria-hidden="true">✓</span> Your report is unlocked and saved</div>
      <section className="vc-section vc-hero"><span className="vc-eyebrow">One more idea for your next chapter</span><h1>{screen.title}</h1><p className="vc-lead">{screen.description}</p><img className="vc-guide-cover" src="/v3/guide-cover.svg" width="440" height="520" alt="Everyday Thinking guide, with a lavender cover and an illustrated path through a garden of ideas." /><div className="vc-pill">A companion to your thinking profile</div></section>
      <section className="vc-section vc-guide-offer"><span className="vc-eyebrow">An invitation to keep exploring</span><h2>Everyday Thinking</h2><p>Four short chapters. A handful of thoughtful exercises. A little room to put your curiosity into practice.</p><div className="vc-big-price"><span className="vc-old-price">{money(offer.guide_comparison_cents)}</span><strong>{money(offer.guide_cents)}</strong><span>one-time guide offer</span></div><button className="vc-button" disabled={busy} onClick={() => onSubmit({ decision: "accept" })}>{busy ? "Saving your choice…" : `Add my guide for ${money(offer.guide_cents)}`}<span aria-hidden="true">→</span></button><button className="vc-decline" disabled={busy} onClick={() => onSubmit({ decision: "decline" })}>Continue with my report only</button><p className="vc-terms">A simulated one-time offer. Your choice is saved in this browser. No payment or delivery is required.</p></section>
      <section className="vc-section"><span className="vc-eyebrow">Inside the guide</span><h2>A small practice<br />for an ordinary day.</h2><div className="vc-chapters">{guideChapters.map(([title, description], index) => <article key={title}><span className="vc-chapter-number">0{index + 1}</span><div><h3>{title}</h3><p>{description}</p></div></article>)}</div></section>
      <section className="vc-section vc-guide-excerpt"><span className="vc-eyebrow">A page to start with</span><h2>“What else could I notice?”</h2><p>Look at an everyday object for a moment. Find three details you usually miss. Put it away, then describe those details from memory. The aim is simply to make a little space for attention.</p><span className="vc-handwritten">A few quiet minutes can be enough.</span></section>
      <section className="vc-section"><span className="vc-eyebrow">A reader’s perspective</span><h2>A little easier to begin.</h2><div className="vc-quotes"><Quote quote="I could open a chapter, choose an exercise and try it without clearing an hour in my day." name="Sam" note="Sample guide feedback" /><Quote quote="The prompts were an invitation to notice something new. That felt like a useful place to start." name="Taylor" note="Sample guide feedback" /></div></section>
      <section className="vc-section vc-reassurance"><span className="vc-reassurance-icon" aria-hidden="true">✧</span><h2>Your pace. Your practice.</h2><p>Choose a chapter that speaks to you. Read it here alongside your report, without an account or anything to install.</p><button className="vc-button" disabled={busy} onClick={() => onSubmit({ decision: "accept" })}>{busy ? "Saving…" : `Yes, add the guide for ${money(offer.guide_cents)}`}<span aria-hidden="true">→</span></button><button className="vc-decline" disabled={busy} onClick={() => onSubmit({ decision: "decline" })}>No thanks, take me to my report</button></section>
      <section className="vc-section"><h2>Good to know.</h2><Faq items={[["How do I read the guide?", "Accept the offer and a readable guide preview appears next to your unlocked report in this browser."], ["Is it a training program?", "It is a short collection of sample reflection exercises, not a clinical intervention or a promise of improved cognitive performance."], ["Can I continue without it?", "Yes. Choose report only and your complete unlocked report remains available."], ["Will I be charged?", "No. Both offer prices are part of this demonstration. No payment information is collected and your choice creates no charge."]]} /></section>
    </div>}

    {final && <div className="vc-content">
      <section className="vc-section vc-hero"><div className="vc-pill"><span aria-hidden="true">✓</span> {run.demo_completed ? "Your visit is complete" : "Ready when you are"}</div><h1>{screen.title}</h1><p className="vc-lead">{screen.description}</p><img className="vc-final-art" src="/v3/report-hero.svg" width="640" height="440" alt="An open profile surrounded by a path of ideas." /><a className="vc-button" href="#vc-full-report">Read my report<span aria-hidden="true">↓</span></a><div className="vc-status-pills"><span>Report unlocked</span><span>{hasGuide ? "Guide added" : "Report only"}</span></div></section>
      <section className="vc-section vc-full-report" id="vc-full-report" aria-labelledby="vc-report-title"><span className="vc-eyebrow">Your saved profile</span><h2 id="vc-report-title">{run.result?.title || "Your thinking profile"}</h2>{run.result ? <><p className="vc-report-summary">{run.result.summary}</p><div className="vc-report-toc" aria-label="Report contents">{run.result.sections.map((section, index) => <a href={`#vc-report-section-${index}`} key={section.title}>{section.title}<span aria-hidden="true">↗</span></a>)}</div>{run.result.sections.map((section, index) => <article className="vc-report-section" id={`vc-report-section-${index}`} key={section.title}><span className="vc-chapter-number">0{index + 1}</span><h3>{section.title}</h3><p>{section.body}</p><div className="vc-practice"><span className="vc-eyebrow">Try this</span><p>{section.practice}</p></div></article>)}<details className="vc-report-note"><summary>About this sample report</summary><p>{run.result.disclaimer}</p></details></> : <p role="status">Your report is not available in this response. Reload this page to restore your saved report.</p>}</section>
      {hasGuide && <section className="vc-section vc-guide-reading" aria-labelledby="vc-guide-title"><div className="vc-guide-reading-heading"><img src="/v3/guide-cover.svg" width="220" height="260" alt="Everyday Thinking guide cover" /><div><span className="vc-eyebrow">Your companion guide</span><h2 id="vc-guide-title">Everyday Thinking</h2><p>A few quiet minutes. One new way to look.</p></div></div><p className="vc-section-intro">Start with any of these four practices. There is no score to reach and no schedule to keep.</p><div className="vc-guide-pages">{[
        ["A quieter starting point", "Before a familiar task, set aside one distraction and take three unhurried breaths. Name what you want to focus on in a single sentence. After a few minutes, notice what caught your attention. There is nothing to correct; simply return to your sentence."],
        ["A place for a memory", "Choose three things you want to remember. Picture a familiar room and place each item in a different corner. Make each image specific: a colour, a sound, a surprising size. Later, walk through the room in your imagination and see which details return."],
        ["A different question", "Pick a small problem you are thinking about. Write your first explanation. Now ask: what would I notice if I looked at this from the other person’s side? Write a second explanation. You do not have to choose immediately; compare what each version makes visible."],
        ["A habit that fits", "Choose the smallest version of a practice you would enjoy repeating. Connect it to something already in your day, like making tea. Try it once. Afterwards, write one thing you noticed. A useful next step can be as simple as doing the same thing again tomorrow."],
      ].map(([title, body], index) => <article key={title}><span className="vc-chapter-number">0{index + 1}</span><h3>{title}</h3><p>{body}</p></article>)}</div></section>}
      <section className="vc-section vc-reassurance"><span className="vc-reassurance-icon" aria-hidden="true">✳</span><h2>Carry one idea with you.</h2><p>Your report{hasGuide ? " and guide stay" : " stays"} available in this browser while your anonymous session is retained. Come back to the part that felt useful.</p>{!run.demo_completed ? <button className="vc-button" disabled={busy} onClick={() => onSubmit({ acknowledged: true })}>{busy ? "Saving…" : "Finish and keep my report"}<span aria-hidden="true">✓</span></button> : <p className="vc-finished" role="status"><span aria-hidden="true">✓</span> All set. Your choices are saved.</p>}</section>
    </div>}
    {footer}
    <dialog ref={dialogRef} className="vc-dialog" aria-labelledby="vc-dialog-title" onCancel={event => { if (busy) event.preventDefault(); else setModal(null); }} onClose={() => setModal(null)}>
      <button className="vc-dialog-close" disabled={busy} aria-label="Close dialog" onClick={() => setModal(null)}>×</button>
      {modal === "checkout" ? <div className="vc-checkout"><span className="vc-eyebrow">Your report is ready</span><h2 id="vc-dialog-title">Unlock a new perspective.</h2><div className="vc-checkout-order"><span>Thinking profile<br /><small>{offer.intro_days}-day introductory access</small></span><strong>{money(offer.intro_cents)}</strong></div><div className="vc-promo"><span aria-hidden="true">✓</span> Promotion applied <strong>Save {savings}</strong></div><div className="vc-payment-placeholder" aria-label="Non-interactive payment method preview"><div className="vc-placeholder-heading"><span aria-hidden="true">▣</span> Payment preview<span className="vc-placeholder-badge">Preview</span></div><div className="vc-placeholder-field">Card details are not required</div><div className="vc-placeholder-row"><div className="vc-placeholder-field">— — / — —</div><div className="vc-placeholder-field">— — —</div></div></div><p className="vc-checkout-note">No payment details are collected. Continue to save your simulated unlock and open your report.</p><button className="vc-button" disabled={busy} onClick={submitUnlock}>{busy ? "Saving your unlock…" : "Unlock my report"}<span aria-hidden="true">→</span></button><p className="vc-terms">Displayed offer: {money(offer.intro_cents)} for {offer.intro_days} days, then {money(offer.renewal_cents)} every {offer.renewal_weeks} weeks. No charge, renewal or subscription is created.</p><button className="vc-inline-link" onClick={() => setModal("information")}>About this offer</button></div> : <div className="vc-information"><span className="vc-eyebrow">A clear picture</span><h2 id="vc-dialog-title">About this experience.</h2><p>BrainSprint is an entertainment demonstration. Your unlocked report is a saved illustrative sample, independent of your answers. It does not measure intelligence, health or cognitive ability.</p><h3>Offers &amp; community stories</h3><p>Prices, comparison prices and introductory terms show how this funnel works. No actual purchase, subscription, renewal or paid entitlement is created. Community names, ratings and quotations are original sample fixtures, not verified reviews or live customer activity.</p><h3>Your report &amp; your privacy</h3><p>There is no account, app download or email delivery. An optional email is retained only with your anonymous answers. Your saved progress, report, offer deadline and guide choice stay with this browser’s session and follow its deletion and retention rules.</p><p>The countdown is saved once. When it expires, the demonstration remains accessible at the displayed introductory price.</p><button className="vc-button" onClick={() => setModal(isPaywall ? "checkout" : null)}>{isPaywall ? "Back to my offer" : "Continue exploring"}<span aria-hidden="true">→</span></button></div>}
    </dialog>
  </div>;
}
