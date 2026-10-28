import type { V3Answer, V3Run, V3Screen } from "./v3";

export type QueuedAnswer = { step: string; answer: V3Answer };
const storageKey = "brainsprint-answer-queue-v1";

export function canQueueAnswer(screen: V3Screen) {
  return ["word_intro", "single_choice", "scale", "timed_reveal", "interstitial"].includes(screen.type);
}

function validAnswer(screen: V3Screen, answer: unknown): answer is V3Answer {
  if (!answer || typeof answer !== "object" || Object.keys(answer).length !== 1) return false;
  if (screen.type === "single_choice" || screen.type === "scale") {
    return "choice" in answer && screen.options?.some(option => option.id === answer.choice) === true;
  }
  return "acknowledged" in answer && answer.acknowledged === true;
}

export function queuedStep(run: V3Run, queue: QueuedAnswer[]) {
  const last = queue.at(-1);
  return last ? run.configuration.steps.find(screen => screen.id === last.step)!.next : run.current_step;
}

// Only an owned bootstrap/response supplies the run. Stored public IDs grant no access.
export function reconcileAnswers(run: V3Run, stored: unknown): QueuedAnswer[] | null {
  if (!Array.isArray(stored) || stored.length > run.configuration.steps.length) return null;
  const remaining: QueuedAnswer[] = [];
  const seen = new Set<string>();
  let next = run.current_step;
  for (const item of stored) {
    if (!item || typeof item !== "object" || typeof item.step !== "string" || seen.has(item.step)) return null;
    const screen = run.configuration.steps.find(screen => screen.id === item.step);
    if (!screen || !screen.enabled || !canQueueAnswer(screen) || !validAnswer(screen, item.answer)) return null;
    seen.add(item.step);
    // A lost response or another tab may already have confirmed this prefix.
    // The immutable server answer always wins; only the unsent suffix is replayed.
    if (!remaining.length && run.completed_steps.includes(item.step)) continue;
    if (item.step !== next || run.demo_completed) return null;
    remaining.push({ step: item.step, answer: item.answer });
    next = screen.next;
  }
  return remaining;
}

export function appendAnswer(run: V3Run, queue: QueuedAnswer[], step: string, answer: V3Answer) {
  return step === queuedStep(run, queue) ? reconcileAnswers(run, [...queue, { step, answer }]) : null;
}

export function readAnswerQueue(run: V3Run): QueuedAnswer[] {
  try {
    const raw = window.sessionStorage.getItem(storageKey);
    if (!raw || raw.length > 16000) return [];
    const stored = JSON.parse(raw);
    if (stored.run !== run.id || stored.version !== run.configuration_version) {
      clearAnswerQueue(); return [];
    }
    return reconcileAnswers(run, stored.answers) || [];
  } catch { return []; }
}

// Persist before showing the successor. A storage failure must not block answering.
export function writeAnswerQueue(run: V3Run, answers: QueuedAnswer[]): boolean {
  try {
    if (answers.length) window.sessionStorage.setItem(storageKey, JSON.stringify({ run: run.id, version: run.configuration_version, answers }));
    else window.sessionStorage.removeItem(storageKey);
    return true;
  } catch { return false; }
}

export function clearAnswerQueue() {
  try { window.sessionStorage.removeItem(storageKey); } catch {}
}
