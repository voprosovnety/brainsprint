"use client";
import { useEffect, useRef, useState } from "react";
import type { KeyboardEvent, ReactNode } from "react";
import type { V3Answer, V3Screen } from "@/lib/v3";

type Props = { screen: V3Screen; busy: boolean; saving: boolean; progress: number; onSubmit: (answer: V3Answer) => void; draft?: V3Answer };
// Text equivalents describe the original diagrams, without judging the visitor's choice.
const diagrams: Record<string, { prompt: string; options: string[] }> = {
  rotation: { prompt: "An L shape turns a quarter turn clockwise each time: upright, right, upside down, then a missing fourth shape.", options: ["L turned left", "Upright L", "L turned right", "Upside-down L"] },
  "growing-dots": { prompt: "Groups of one, three and six dots form growing triangles. The fourth group is missing.", options: ["Eight dots", "Ten dots in four rows", "Nine dots in a square", "Twelve dots"] },
  "alternating-fill": { prompt: "A hexagon is filled, outlined, filled, then missing.", options: ["Filled circle", "Filled hexagon", "Outlined hexagon", "Outlined triangle"] },
  "moving-corner": { prompt: "A dot moves around a square: top left, top right, bottom right, then missing.", options: ["Dot at top right", "Dot at bottom left", "Dot in the center", "Dot at top left"] },
  "nested-shapes": { prompt: "One, two and three concentric squares. The next group is missing.", options: ["Two concentric squares", "Four concentric squares", "Three concentric circles", "Five concentric squares"] },
  "arrow-cycle": { prompt: "An arrow points up, right, down, then the next direction is missing.", options: ["Arrow pointing up", "Arrow pointing right", "Arrow pointing down", "Arrow pointing left"] },
  "line-addition": { prompt: "A vertical line plus a horizontal line makes a cross. A cross plus a diagonal line has a missing result.", options: ["Cross with a rising diagonal", "Cross only", "An X", "A single diagonal"] },
  mirror: { prompt: "A right-facing F shape is shown left of a vertical mirror. Choose its reflection on the right.", options: ["Original F", "F rotated upside down", "F reflected left to right", "F turned right"] },
  "shape-subtraction": { prompt: "A square, triangle and circle minus a triangle equals a missing group.", options: ["Square and triangle", "Triangle and circle", "Circle alone", "Square and circle"] },
  "matrix-count": { prompt: "A two-row matrix has dot counts one, two, three in row one; two, three, missing in row two.", options: ["Two dots", "Four dots", "Five dots", "Six dots"] },
  "polygon-growth": { prompt: "A triangle, square and pentagon gain one side each. The next shape is missing.", options: ["Hexagon", "Pentagon", "Octagon", "Triangle"] },
  "folded-paper": { prompt: "A square folds in half from left to right. A hole is punched near the folded paper's outer right edge. Choose the unfolded hole pattern.", options: ["One hole at the center", "Two horizontally mirrored holes", "Two vertically aligned holes", "Four corner holes"] },
  overlap: { prompt: "Two circles overlap. The central shared lens is marked. Choose the shape of that shared area.", options: ["Two joined circles", "A crescent", "An oval lens", "One full circle"] },
  "size-rhythm": { prompt: "Circles repeat small, medium, large; small, medium, then missing.", options: ["Small circle", "Medium circle", "Large circle", "Two small circles"] },
  "diagonal-motion": { prompt: "A dot advances down the main diagonal of a four-by-four grid, occupying first, second and third cells in turn. The next position is missing.", options: ["Top-right cell", "Bottom-left cell", "Center-left cell", "Bottom-right cell"] },
  "sector-turn": { prompt: "A filled quarter-circle turns clockwise: top-right, bottom-right, bottom-left, then missing.", options: ["Top-left sector", "Top-right sector", "Bottom-left sector", "Bottom half"] },
  balance: { prompt: "One square balances three dots. Two squares balance a missing group of dots.", options: ["Three dots", "Four dots", "Six dots", "Eight dots"] },
  "cube-net": { prompt: "A cube net has a star in its central face, a circle above, triangle right, diamond left, square below and plus at the far bottom. Which symbol is opposite the star when folded?", options: ["Circle", "Triangle", "Plus", "Diamond"] },
  path: { prompt: "A route turns right then down. The same route after a quarter-turn clockwise starts down. Which second direction completes it?", options: ["Right then up", "Down then left", "Down then right", "Up then right"] },
  symmetry: { prompt: "The left half of a butterfly has one large upper wing and a small lower wing, each with a dot. The matching right half is missing.", options: ["Large lower wing and small upper wing", "Large upper wing and small lower wing, with matching dots", "Two equal wings", "A single wing"] },
  "tile-composition": { prompt: "Four quarter-circle tiles make a circle in a two-by-two square. The bottom-right tile is missing.", options: ["Quarter-circle from top-left corner", "Quarter-circle from top-right corner", "Quarter-circle from bottom-left corner", "Quarter-circle from bottom-right corner"] },
  "two-rules": { prompt: "A triangle with one dot, square with two dots, pentagon with three dots, then a missing shape.", options: ["Hexagon with three dots", "Pentagon with four dots", "Hexagon with four dots", "Circle with four dots"] },
  "final-matrix": { prompt: "A three-by-three matrix: triangles in row one, squares in row two, circles in row three. Columns contain one, two and three inner dots. Bottom-right is missing.", options: ["Circle with two dots", "Square with three dots", "Triangle with three dots", "Circle with three dots"] },
};
function keyFor(screen: V3Screen) { return screen.id.replace(/^visual-/, ""); }
export function V3Quiz(props: Props) {
  const { screen } = props;
  if (screen.type === "single_choice" || screen.type === "scale") return <Choices {...props} />;
  if (screen.type === "timed_reveal") return <Reveal {...props} />;
  if (screen.type === "processing") return <Processing {...props} />;
  if (screen.type === "email") return <Email {...props} />;
  return <section className={`v3-question v3-${screen.type}`}>
    <div className="v3-question-intro"><p className="v3-eyebrow">{screen.type === "word_intro" ? "A little exercise in remembering" : screen.type === "confirmation" ? "Your answers, all together" : "A moment of perspective"}</p><h1>{screen.title}</h1><p>{screen.description}</p></div>
    {screen.type === "word_intro" ? <div className="v3-word-card"><span aria-hidden="true" className="v3-word-spark">✳</span><strong>{screen.word}</strong><span>Keep it in mind for later</span></div> : screen.art ? <img className="v3-community-art" src={screen.art} decoding="sync" loading="eager" alt="People connected by their curiosity, an original illustration" /> : screen.type === "confirmation" ? <div className="v3-confirmation-art"><span aria-hidden="true">✓</span><strong>41 responses ready</strong><p>Words, numbers, shapes — and your perspective.</p></div> : <div className="v3-break-art" aria-hidden="true"><i /><i /><i /><span>✳</span></div>}
    {screen.id === "emerging-profile" && <div className="v3-interstitial-progress"><span style={{ width: `${props.progress}%` }} /><p>{props.progress}% of your quiz responses are answered</p></div>}
    {screen.id === "nearly-there" && <p className="v3-soft-note">{props.progress}% of your responses answered</p>}
    <div className="v3-actions"><button className="v3-primary" disabled={props.busy} onClick={() => props.onSubmit({ acknowledged: true })}>{props.saving ? "Saving…" : screen.cta || "Continue"}<span aria-hidden="true">→</span></button>{screen.type === "word_intro" && <p>One task at a time. Go at your own pace.</p>}</div>
  </section>;
}
function Choices({ screen, busy, saving, onSubmit, draft }: Props) {
  const [selected, setSelected] = useState(draft && "choice" in draft ? draft.choice : "");
  const group = useRef<HTMLDivElement>(null);
  const options = screen.options || [];
  const image = screen.presentation === "image";
  const objects = screen.presentation === "objects";
  const description = diagrams[keyFor(screen)];
  function keyboard(event: KeyboardEvent<HTMLButtonElement>, index: number) {
    if (!["ArrowDown", "ArrowUp", "ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key) || busy) return;
    event.preventDefault();
    const next = event.key === "Home" ? 0 : event.key === "End" ? options.length - 1 : (index + (["ArrowDown", "ArrowRight"].includes(event.key) ? 1 : -1) + options.length) % options.length;
    setSelected(options[next].id);
    group.current?.querySelectorAll<HTMLButtonElement>("[role=radio]")[next]?.focus();
  }
  return <section className={`v3-question ${image ? "v3-visual-question" : ""}`} aria-busy={saving}>
    <div className="v3-question-intro"><p className="v3-eyebrow">{screen.role === "demographic" ? "A little about you" : screen.type === "scale" ? "Your perspective" : image ? "Look a little closer" : "Follow your curiosity"}</p><h1 id="v3-question-title">{screen.title}</h1><p>{screen.description}</p></div>
    {screen.asset && <div className="v3-puzzle"><img src={screen.asset} decoding="sync" loading="eager" alt={description?.prompt || screen.asset_alt || "An illustrated landmark"} /></div>}
    <div ref={group} className={`v3-options ${image ? "v3-image-options" : objects ? "v3-object-options" : screen.type === "scale" ? "v3-scale-options" : ""}`} role="radiogroup" aria-labelledby="v3-question-title">
      {options.map((option, i) => <button key={option.id} type="button" role="radio" aria-checked={selected === option.id} disabled={busy} tabIndex={selected ? selected === option.id ? 0 : -1 : i === 0 ? 0 : -1} onKeyDown={e => keyboard(e, i)} className={`v3-option ${selected === option.id ? "is-selected" : ""}`} onClick={() => { setSelected(option.id); onSubmit({ choice: option.id }); }}>
        {option.asset && <img src={option.asset} decoding="sync" loading="eager" alt={description?.options[i] || `Option ${option.label}`} />}
        {objects && <ObjectDrawing label={option.label} />}
        <span>{screen.type === "scale" && <span className="v3-scale-number" aria-hidden="true">{i + 1}</span>}{option.label}</span><span className="v3-option-indicator" aria-hidden="true">{selected === option.id ? "✓" : ""}</span>
      </button>)}
    </div>
    <p className="v3-selection-hint">Choose an answer to continue. Use arrow keys to select, then Continue.</p>
    <div className="v3-actions"><button className="v3-primary" disabled={busy || !selected} onClick={() => onSubmit({ choice: selected })}>{saving ? "Saving your answer…" : "Continue"}<span aria-hidden="true">→</span></button></div>
  </section>;
}
function Reveal({ screen, busy, saving, onSubmit }: Props) {
  const [remaining, setRemaining] = useState(screen.reveal_seconds || 5);
  const submitted = useRef(false);
  useEffect(() => {
    const interval = window.setInterval(() => { if (document.visibilityState === "visible") setRemaining(value => Math.max(0, value - 1)); }, 1000);
    return () => clearInterval(interval);
  }, []);
  useEffect(() => { if (remaining === 0 && !busy && !submitted.current) { submitted.current = true; onSubmit({ acknowledged: true }); } }, [remaining, busy, onSubmit]);
  return <section className="v3-question v3-reveal"><div className="v3-question-intro"><p className="v3-eyebrow">A moment for your memory</p><h1>{screen.title}</h1><p>{screen.description}</p></div><div className="v3-digits" aria-label={remaining ? screen.digits?.split("").join(" ") : "The digits are now hidden"}>{remaining ? screen.digits : "•".repeat(screen.digits?.length || 5)}</div><p className="v3-soft-note" role="status">{remaining ? `${remaining} seconds to remember` : "Your next question is ready"}</p><div className="v3-reveal-track" aria-hidden="true"><span style={{ width: `${remaining / (screen.reveal_seconds || 5) * 100}%` }} /></div><div className="v3-actions"><button className="v3-primary" disabled={busy || remaining > 0} onClick={() => onSubmit({ acknowledged: true })}>{saving ? "Saving…" : remaining ? "Keep the digits in mind" : "Continue"}</button></div></section>;
}
function Processing({ screen, busy, saving, onSubmit }: Props) {
  const [seconds, setSeconds] = useState(0);
  const [preferences, setPreferences] = useState<Partial<Record<"focus" | "practice", "yes" | "no">>>({});
  const [info, setInfo] = useState(false);
  const dialog = useRef<HTMLDialogElement>(null);
  const autoSubmitted = useRef(false);
  const reduceMotion = useRef(false);
  const prompts = screen.prompts || [];
  const duration = screen.duration_seconds || 5;
  const nextPrompt = prompts.find((p, i) => !preferences[p.id] && seconds >= duration * (i + 1) / (prompts.length + 1));
  const complete = seconds >= duration && prompts.every(p => !!preferences[p.id]);
  const modal = !!nextPrompt || info;
  useEffect(() => { reduceMotion.current = window.matchMedia("(prefers-reduced-motion: reduce)").matches; }, []);
  useEffect(() => {
    if (modal || complete) return;
    const timer = window.setInterval(() => { if (document.visibilityState === "visible") setSeconds(value => Math.min(duration, value + (reduceMotion.current ? duration : 0.25))); }, 250);
    return () => clearInterval(timer);
  }, [modal, complete, duration]);
  useEffect(() => {
    if (modal && !dialog.current?.open) dialog.current?.showModal();
    else if (!modal && dialog.current?.open) dialog.current?.close();
  }, [modal]);
  useEffect(() => { if (complete && !prompts.length && !busy && !autoSubmitted.current) { autoSubmitted.current = true; onSubmit({ acknowledged: true }); } }, [complete, busy, onSubmit, prompts.length]);
  const stage = Math.min((screen.stages?.length || 1) - 1, Math.floor(seconds / duration * (screen.stages?.length || 1)));
  function finish() {
    if (preferences.focus && preferences.practice) onSubmit({ focus: preferences.focus, practice: preferences.practice });
    else if (!prompts.length) onSubmit({ acknowledged: true });
  }
  return <section className="v3-question v3-processing"><div className="v3-question-intro"><p className="v3-eyebrow">Your report, coming together</p><h1>{screen.title}</h1><p>{screen.description}</p></div><div className="v3-preparation-orbit" aria-hidden="true"><i /><i /><i /><strong>✳</strong></div><div className="v3-processing-progress" role="progressbar" aria-label="Preparing your report" aria-valuenow={Math.round(seconds / duration * 100)} aria-valuemin={0} aria-valuemax={100}><span style={{ width: `${seconds / duration * 100}%` }} /></div><ol className="v3-stages">{screen.stages?.map((label, i) => <li key={label} className={i <= stage ? "active" : ""}><span aria-hidden="true">{i < stage || complete ? "✓" : i === stage ? "◌" : "○"}</span>{label}</li>)}</ol><p className="v3-sr-only" role="status">{complete ? "Your report preparation is complete" : screen.stages?.[stage]}</p><button className="v3-text-button v3-how" onClick={() => setInfo(true)}>ⓘ About your report</button><div className="v3-actions"><button className="v3-primary" disabled={busy || !complete} onClick={finish}>{saving ? "Saving…" : complete ? "Continue" : "Preparing your report…"}<span aria-hidden="true">→</span></button></div>
    <dialog ref={dialog} className="v3-dialog" aria-labelledby="v3-dialog-title" onCancel={event => { if (nextPrompt) event.preventDefault(); else setInfo(false); }} onClose={() => setInfo(false)}>{nextPrompt ? <><span className="v3-dialog-symbol" aria-hidden="true">✧</span><p className="v3-eyebrow">One quick question</p><h2 id="v3-dialog-title">{nextPrompt.title}</h2><p>This helps save your preferences alongside your report.</p><div className="v3-modal-options"><button className="v3-primary" onClick={() => setPreferences(value => ({ ...value, [nextPrompt.id]: "yes" }))}>Yes, I would</button><button className="v3-secondary" onClick={() => setPreferences(value => ({ ...value, [nextPrompt.id]: "no" }))}>No, thank you</button></div></> : <><h2 id="v3-dialog-title">A fresh perspective, for curiosity.</h2><p>Your report is an illustrative sample. It is independent of answer correctness and does not measure intelligence or cognitive ability. Your two preferences are saved with this visit.</p><button className="v3-primary" onClick={() => setInfo(false)}>Got it</button></>}</dialog>
  </section>;
}
function Email({ screen, busy, saving, onSubmit }: Props) {
  const [email, setEmail] = useState("");
  return <section className="v3-question v3-email"><div className="v3-question-intro"><p className="v3-eyebrow">A place for your perspective</p><h1>{screen.title}</h1><p>{screen.description}</p></div><div className="v3-envelope" aria-hidden="true"><span>✳</span></div><form onSubmit={e => { e.preventDefault(); onSubmit({ email: email.trim() || null }); }}><label htmlFor="report-email">Email address <span>(optional)</span></label><input id="report-email" type="email" inputMode="email" autoComplete="email" maxLength={254} placeholder={screen.sample_email || "reader@example.com"} value={email} disabled={busy} onChange={e => setEmail(e.target.value)} /><button type="button" className="v3-text-button" disabled={busy} onClick={() => setEmail(screen.sample_email || "reader@example.com")}>Use the sample address</button><p className="v3-email-note">Your report opens in this browser. This address stays with your saved visit; no email is sent.</p><div className="v3-actions"><button className="v3-primary" type="submit" disabled={busy}>{saving ? "Saving…" : screen.cta || "Continue"}<span aria-hidden="true">→</span></button><button className="v3-text-button" type="button" disabled={busy} onClick={() => onSubmit({ email: null })}>Skip for now</button></div></form></section>;
}
function ObjectDrawing({ label }: { label: string }) {
  const shapes: Record<string, ReactNode> = {
    Violin: <><path d="M45 33C17 20 17 49 31 54C11 66 24 91 48 82C72 91 85 66 65 54C79 49 79 20 51 33" /><path d="M44 8h8v48h-8zM40 70h16M48 13v65" /></>,
    Trumpet: <><path d="M14 45h47L85 26v48L61 56H14zM25 56v20h34V56M33 36v10M44 36v10M55 36v10" /></>,
    Piano: <><path d="M17 35Q39 8 80 19v60H17z" /><path d="M17 58h63M29 58v21M41 58v21M53 58v21M65 58v21M23 58v12M35 58v12M59 58v12M71 58v12" /></>,
    Guitar: <><path d="M51 39C33 26 20 46 29 58C11 74 34 96 50 77C68 84 79 63 61 52L75 17l-9-4z" /><circle cx="48" cy="60" r="7" /><path d="M46 72l25-54M39 76l14 4" /></>,
    Flute: <><path d="M20 80L71 13l8 6L28 86zM20 80l8 6M69 20l7 5" /><path d="M35 68l5 4M43 57l5 4M51 46l5 4M59 35l5 4" /></>,
    Telescope: <><path d="M19 36l53-19 9 21-53 20zM72 17l9-3 9 21-9 3M42 56l-15 32M47 56l20 32M45 58v30" /></>,
    Maple: <><path d="M50 75L22 61l9-9-15-12 18-2-2-18 15 10L52 9l9 21 14-8-1 17 17 1-17 16 6 9z" fill="#c6ccfb" /><path d="M50 88V40M50 68L31 51M50 59l21-14" /></>,
    Willow: <><path d="M50 87V29M27 86Q0 31 49 22Q98 30 77 86M37 78Q19 34 49 22Q78 33 64 80M17 63Q17 18 49 22Q84 15 89 64" /></>,
    Oak: <><path d="M48 73C11 77 13 48 27 43C10 20 41 12 50 24C68 6 89 26 77 43C96 59 70 80 48 73" fill="#c6ccfb" /><path d="M49 88V40M49 60L31 47M49 55l19-16" /></>,
    Birch: <><path d="M43 87l3-56h11l3 56z" /><path d="M48 42h7M45 54h6M51 66h8M44 79h7" /><path d="M20 39Q6 13 35 18Q51 1 65 18Q93 13 82 40Q68 56 49 45Q31 57 20 39" fill="#c6ccfb" /></>,
    Pine: <><path d="M50 12L31 34h10L22 56h12L12 78h76L67 56h11L59 34h10z" fill="#c6ccfb" /><path d="M49 78v14" /></>,
    Rose: <><path d="M50 89V50M50 73q22-23 31-6-12 15-31 6M50 82Q23 68 23 58q23-3 27 24" /><path d="M50 17C26 1 14 29 33 42C27 65 61 64 64 46C89 44 84 14 63 18C65 3 43 3 50 17Z" fill="#c6ccfb" /><path d="M50 23q-19-4-10 14q3 11 15 2q13-13-5-16z" /></>,
  };
  return <svg viewBox="0 0 100 100" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">{shapes[label] || <circle cx="50" cy="50" r="28" />}</svg>;
}
