import assert from "node:assert/strict";
import { test } from "node:test";
import { api } from "../src/lib/api.ts";

async function withStalledResponse(headersReceived) {
  const original = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch };
  let requestSignal;
  globalThis.window = {
    setTimeout(callback, delay) {
      assert.equal(delay, 30_000);
      return setTimeout(callback, 1);
    },
  };
  globalThis.document = { cookie: "" };
  globalThis.fetch = async (_url, { signal }) => {
    requestSignal = signal;
    const stalled = () => new Promise((_resolve, reject) => {
      signal.addEventListener("abort", () => reject(new DOMException("Aborted", "AbortError")), { once: true });
    });
    return headersReceived ? { ok: true, json: stalled } : stalled();
  };
  try {
    await assert.rejects(api("runs/example/steps/example/complete", { answer: { choice: "b" } }), /took too long to respond/);
    assert.equal(requestSignal.aborted, true);
  } finally {
    for (const [key, value] of Object.entries(original)) {
      if (value === undefined) delete globalThis[key];
      else globalThis[key] = value;
    }
  }
}

test("a missing API response becomes a retryable timeout", () => withStalledResponse(false));
test("an API body that stalls after headers also times out", () => withStalledResponse(true));
