import { useEffect, useRef, useState } from "react";
import type { Answer, LogicScreen, MemoryScreen, PatternScreen } from "@/lib/api";

export function Arrow() { return <span aria-hidden="true">↗</span>; }
export function Grid({ pattern, size = 4, interactive = false, disabled = false, onToggle }: {
  pattern: number[]; size?: number; interactive?: boolean; disabled?: boolean; onToggle?: (cell: number) => void;
}) {
  return <div className={`memory-grid ${interactive ? "is-interactive" : ""}`} style={{ gridTemplateColumns: `repeat(${size}, 1fr)` }} role={interactive ? "group" : "img"} aria-label={interactive ? "Select the cells you remember" : `Highlighted cells: ${pattern.map(c => `row ${Math.floor(c / size) + 1} column ${c % size + 1}`).join(", ")}`}>
    {Array.from({ length: size * size }, (_, cell) => interactive ?
      <button key={cell} disabled={disabled} className={`cell ${pattern.includes(cell) ? "lit" : ""}`} aria-label={`Row ${Math.floor(cell / size) + 1}, column ${cell % size + 1}`} aria-pressed={pattern.includes(cell)} onClick={() => onToggle?.(cell)}><span aria-hidden="true">{pattern.includes(cell) ? "✦" : ""}</span></button> :
      <span key={cell} className={`cell ${pattern.includes(cell) ? "lit" : ""}`}><span aria-hidden="true">{pattern.includes(cell) ? "✦" : ""}</span></span>)}
  </div>;
}
type ActionProps = { locked: boolean; saving: boolean; save: (answer: Answer) => void };
export function Memory({ screen, restored, locked, saving, save }: ActionProps & { screen: MemoryScreen; restored: boolean }) {
  const [phase, setPhase] = useState<"ready" | "reveal" | "recall">("ready");
  const [remaining, setRemaining] = useState(screen.reveal_seconds);
  const [cells, setCells] = useState<number[]>([]);
  const heading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    if (phase !== "reveal") return;
    const deadline = Date.now() + screen.reveal_seconds * 1000;
    const timer = setInterval(() => {
      const left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
      setRemaining(left);
      if (!left) setPhase("recall");
    }, 100);
    return () => clearInterval(timer);
  }, [phase, screen.reveal_seconds]);
  useEffect(() => { if (phase !== "ready") heading.current?.focus(); }, [phase]);
  return <>
    <div className="challenge-instructions">
      <h2 ref={heading} tabIndex={-1}>{phase === "ready" ? "Watch. Remember. Rebuild." : phase === "reveal" ? "Let the shape settle in." : "Where were the blue cells?"}</h2>
      <p>{phase === "ready" ? `The pattern appears for ${screen.reveal_seconds} seconds. Then it is your turn. No timer on your answer.` : phase === "reveal" ? "Notice the whole shape, not just each square." : "Tap to select. Tap again to change your mind."}</p>
    </div>
    {restored && phase === "ready" && <p className="recovery-note">Your saved steps are safe. This unfinished round starts with a fresh reveal.</p>}
    <Grid size={screen.grid_size} pattern={phase === "reveal" ? screen.pattern : cells} interactive={phase === "recall"} disabled={locked} onToggle={c => setCells(previous => previous.includes(c) ? previous.filter(i => i !== c) : [...previous, c])} />
    <div className="challenge-actions">
      {phase === "ready" && <button className="button" disabled={locked} onClick={() => setPhase("reveal")}>Show the pattern <Arrow /></button>}
      {phase === "reveal" && <div className="countdown" role="status"><span>{remaining}</span> seconds to take it in</div>}
      {phase === "recall" && <><div className="selection-count"><span>{cells.length} {cells.length === 1 ? "cell" : "cells"} selected</span><button className="text-button" disabled={locked} onClick={() => setCells([])}>Clear selection</button></div><button className="button" disabled={locked} onClick={() => save({ cells })}>{saving ? "Saving your answer…" : screen.type === "tutorial" ? "Save practice answer" : "Save and continue"}<Arrow /></button></>}
    </div>
  </>;
}
export function Pattern({ screen, locked, saving, save }: ActionProps & { screen: PatternScreen }) {
  const [choice, setChoice] = useState("");
  return <>
    <div className="sequence" role="img" aria-label={`Sequence: ${screen.sequence.join(", ")}, then an empty place`}>
      {screen.sequence.map((symbol, index) => <span key={index} aria-hidden="true">{symbol}</span>)}<span className="sequence-gap" aria-hidden="true">?</span>
    </div>
    <fieldset className="pattern-options" disabled={locked}><legend>Choose the next element</legend><div>
      {screen.options.map(option => <button key={option.id} type="button" aria-pressed={choice === option.id} className={`pattern-option ${choice === option.id ? "chosen" : ""}`} onClick={() => setChoice(option.id)}><span aria-hidden="true">{option.symbol}</span><small>{option.label}</small></button>)}
    </div></fieldset>
    <div className="challenge-actions"><button className="button" disabled={locked || !choice} onClick={() => save({ choice })}>{saving ? "Saving your answer…" : "Save and continue"}<Arrow /></button></div>
  </>;
}
export function Logic({ screen, locked, saving, save }: ActionProps & { screen: LogicScreen }) {
  const [order, setOrder] = useState(screen.cards.map(c => c.id));
  const [picked, setPicked] = useState<string | null>(null);
  const [announcement, setAnnouncement] = useState("");
  const [dropTarget, setDropTarget] = useState<string | null>(null);
  const pointer = useRef<{ id: string; x: number; y: number } | null>(null);
  const suppressClick = useRef(false);
  function move(id: string, to: number) {
    if (locked) return;
    setOrder(current => {
      const next = current.filter(c => c !== id);
      next.splice(to, 0, id);
      return next;
    });
    setPicked(null);
    setAnnouncement(`${screen.cards.find(c => c.id === id)?.label} moved to position ${to + 1}.`);
  }
  function swap(id: string) {
    if (suppressClick.current) { suppressClick.current = false; return; }
    if (picked && picked !== id) {
      setOrder(current => current.map(c => c === id ? picked : c === picked ? id : c));
      setPicked(null);
      setAnnouncement("Cards swapped.");
    } else setPicked(picked === id ? null : id);
  }
  return <>
    <ul className="logic-rules">{screen.rules.map(rule => <li key={rule}>{rule}</li>)}</ul>
    <p className="interaction-hint">Tap two cards to swap · drag to move · arrow buttons to reorder</p>
    <ol className={`logic-cards cards-${order.length}`}>
      {order.map((id, index) => {
        const card = screen.cards.find(c => c.id === id)!;
        return <li key={id} data-card-id={id} className={`${picked === id ? "picked" : ""} ${dropTarget === id ? "drop-target" : ""}`}>
          <span className="position-label">{index + 1}</span>
          <button className="logic-tile" disabled={locked} aria-label={`${card.label}, position ${index + 1}. Select to swap. Use left or right arrow to move.`} aria-pressed={picked === id} draggable={false}
            onPointerDown={e => {
              if (!locked) {
                suppressClick.current = false;
                pointer.current = { id, x: e.clientX, y: e.clientY };
                e.currentTarget.setPointerCapture(e.pointerId);
              }
            }}
            onPointerMove={e => {
              const start = pointer.current;
              if (start && Math.hypot(e.clientX - start.x, e.clientY - start.y) > 12) {
                setDropTarget(document.elementFromPoint(e.clientX, e.clientY)?.closest<HTMLElement>("[data-card-id]")?.dataset.cardId || null);
              }
            }}
            onPointerUp={e => {
              const start = pointer.current; pointer.current = null; setDropTarget(null);
              if (!start || locked) return;
              if (Math.hypot(e.clientX - start.x, e.clientY - start.y) > 12) {
                const target = document.elementFromPoint(e.clientX, e.clientY)?.closest<HTMLElement>("[data-card-id]")?.dataset.cardId;
                if (target) move(start.id, order.indexOf(target));
                suppressClick.current = true;
              }
            }} onPointerCancel={() => { pointer.current = null; setDropTarget(null); }}
            onKeyDown={e => { if ((e.key === "ArrowLeft" && index > 0) || (e.key === "ArrowRight" && index < order.length - 1)) { e.preventDefault(); move(id, index + (e.key === "ArrowLeft" ? -1 : 1)); } }}
            onClick={() => swap(id)}><span aria-hidden="true">{card.symbol}</span><small>{card.label}</small></button>
          <div className="reorder-controls"><button disabled={locked || index === 0} aria-label={`Move ${card.label} left`} onClick={() => move(id, index - 1)}>←</button><button disabled={locked || index === order.length - 1} aria-label={`Move ${card.label} right`} onClick={() => move(id, index + 1)}>→</button></div>
        </li>;
      })}
    </ol>
    <p className="logic-status" role="status">{announcement || (picked ? "Now select another card to swap places." : "Every card has a place. Take your time.")}</p>
    <div className="challenge-actions"><button className="button" disabled={locked} onClick={() => save({ order })}>{saving ? "Saving your answer…" : "Save and continue"}<Arrow /></button></div>
  </>;
}
