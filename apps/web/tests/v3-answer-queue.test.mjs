import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { appendAnswer, canQueueAnswer, clearAnswerQueue, queuedStep, readAnswerQueue, reconcileAnswers, writeAnswerQueue } from "../src/lib/v3-answer-queue.ts";

const configuration = JSON.parse(readFileSync(new URL("../../api/resources/funnel/brainsprint-v3.json", import.meta.url), "utf8"));
const run = {
  id: "owned-run", configuration_version: "published-v3", configuration,
  current_step: configuration.entry, completed_steps: [], answers: {}, demo_completed: false,
};
function quizQueue() {
  let queue = [];
  for (let count = 0; count < configuration.steps.length; count++) {
    const screen = configuration.steps.find(screen => screen.id === queuedStep(run, queue));
    if (!canQueueAnswer(screen)) return queue;
    const answer = screen.options ? { choice: screen.options[0].id } : { acknowledged: true };
    queue = appendAnswer(run, queue, screen.id, answer);
    assert.ok(queue);
  }
  assert.fail("The quiz must stop at a server-confirmed boundary");
}
function confirmed(count, queue) {
  const value = structuredClone(run);
  for (const item of queue.slice(0, count)) {
    value.completed_steps.push(item.step);
    value.answers[item.step] = item.answer;
    value.current_step = configuration.steps.find(screen => screen.id === item.step).next;
  }
  return value;
}
function withStorage(callback) {
  const original = globalThis.window;
  const values = new Map();
  globalThis.window = { sessionStorage: {
    getItem: key => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: key => values.delete(key),
  } };
  try { callback(values); }
  finally {
    if (original === undefined) delete globalThis.window;
    else globalThis.window = original;
  }
}

test("all 41 quiz choices can be queued before the first server confirmation", () => {
  const queue = quizQueue();
  assert.equal(queue.filter(item => "choice" in item.answer).length, 41);
  assert.equal(queuedStep(run, queue), "confirm-answers");
  assert.deepEqual(run.completed_steps, []);
  assert.equal(appendAnswer(run, queue, "confirm-answers", { acknowledged: true }), null);
});

test("duplicate taps and future answers cannot replace or skip a queued answer", () => {
  const queue = appendAnswer(run, [], configuration.entry, { acknowledged: true });
  assert.equal(appendAnswer(run, queue, configuration.entry, { acknowledged: true }), null);
  assert.equal(appendAnswer(run, queue, "visual-growing-dots", { choice: "a" }), null);
  assert.deepEqual(queue, [{ step: configuration.entry, answer: { acknowledged: true } }]);
});

test("choices must belong to the current question and contain no extra fields", () => {
  const queue = quizQueue().slice(0, 1);
  const step = queuedStep(run, queue);
  assert.equal(appendAnswer(run, queue, step, { choice: "foreign-choice" }), null);
  assert.equal(appendAnswer(run, queue, step, { choice: configuration.steps[1].options[0].id, email: "private@example.com" }), null);
  assert.equal(appendAnswer(run, [], configuration.entry, { acknowledged: false }), null);
});

test("a confirmed prefix is removed while every later answer remains unchanged", () => {
  const queue = quizQueue();
  const value = confirmed(7, queue);
  const remaining = reconcileAnswers(value, queue);
  assert.deepEqual(remaining, queue.slice(7));
  assert.equal(queuedStep(value, remaining), "confirm-answers");
  assert.deepEqual(reconcileAnswers(confirmed(queue.length, queue), queue), []);
});

test("server-confirmed answers from another tab win without discarding the unsent suffix", () => {
  const queue = quizQueue();
  const value = confirmed(2, queue);
  value.answers[queue[1].step] = { choice: configuration.steps[1].options[1].id };
  assert.deepEqual(reconcileAnswers(value, queue), queue.slice(2));
});

test("reconciliation rejects reordered, repeated, unknown and commercial entries", () => {
  const queue = quizQueue();
  assert.equal(reconcileAnswers(run, [queue[1], queue[0]]), null);
  assert.equal(reconcileAnswers(run, [queue[0], queue[0]]), null);
  assert.equal(reconcileAnswers(run, [{ step: "unknown", answer: { acknowledged: true } }]), null);
  assert.equal(reconcileAnswers({ ...run, current_step: "report-email" }, [{ step: "report-email", answer: { email: "private@example.com" } }]), null);
  assert.equal(reconcileAnswers({ ...run, current_step: "report-offer" }, [{ step: "report-offer", answer: { offer_id: "report-intro-v3", acknowledged: true } }]), null);
  assert.equal(reconcileAnswers(run, Array(configuration.steps.length + 1).fill(queue[0])), null);
});

test("reload recovers the tab queue and removes an already-confirmed lost response", () => withStorage(() => {
  const queue = quizQueue();
  assert.equal(writeAnswerQueue(run, queue), true);
  assert.deepEqual(readAnswerQueue(run), queue);
  const value = confirmed(3, queue);
  assert.deepEqual(readAnswerQueue(value), queue.slice(3));
  assert.equal(queuedStep(value, readAnswerQueue(value)), "confirm-answers");
}));

test("drafts cannot cross run ownership or immutable configuration versions", () => withStorage(values => {
  const queue = quizQueue();
  writeAnswerQueue(run, queue);
  assert.deepEqual(readAnswerQueue({ ...run, id: "another-owned-run" }), []);
  assert.equal(values.size, 0);
  writeAnswerQueue(run, queue);
  assert.deepEqual(readAnswerQueue({ ...run, configuration_version: "different-publication" }), []);
  assert.equal(values.size, 0);
}));

test("corrupt, oversized and retired drafts never enable future screens", () => withStorage(values => {
  writeAnswerQueue(run, quizQueue());
  const key = [...values.keys()][0];
  for (const raw of ["{", "null", "x".repeat(16001), JSON.stringify({ run: run.id, version: run.configuration_version, answers: [{ step: "report-offer", answer: { acknowledged: true } }] })]) {
    values.set(key, raw);
    assert.deepEqual(readAnswerQueue(run), []);
  }
  assert.equal(reconcileAnswers({ ...run, demo_completed: true }, quizQueue()), null);
}));

test("confirmation and explicit data deletion clear the tab queue", () => withStorage(values => {
  writeAnswerQueue(run, quizQueue());
  assert.equal(writeAnswerQueue(run, []), true);
  assert.equal(values.size, 0);
  writeAnswerQueue(run, quizQueue());
  clearAnswerQueue();
  assert.equal(values.size, 0);
}));

test("unavailable storage is reported without preventing in-memory progression", () => withStorage(() => {
  Object.defineProperty(globalThis.window, "sessionStorage", { get() { throw new Error("Storage unavailable"); } });
  const queue = quizQueue();
  assert.equal(writeAnswerQueue(run, queue), false);
  assert.equal(queuedStep(run, queue), "confirm-answers");
  assert.deepEqual(readAnswerQueue(run), []);
  assert.doesNotThrow(clearAnswerQueue);
}));
