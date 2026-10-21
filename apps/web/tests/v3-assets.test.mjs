import assert from "node:assert/strict";
import { test } from "node:test";
import { prepareScreenAssets, screenAssetsReady } from "../src/lib/v3-assets.ts";

test("a timed-out SVG request is released before retrying the same URL", async () => {
  const original = { window: globalThis.window, Image: globalThis.Image };
  const pending = new Map();
  let requests = 0;
  globalThis.window = {
    setTimeout(callback, delay) {
      assert.equal(delay, 15_000);
      return setTimeout(callback, 1);
    },
  };
  globalThis.Image = class {
    set src(source) {
      this.source = source;
      // Browsers coalesce requests for the same image URL while it is pending.
      if (pending.has(source)) return;
      pending.set(source, this);
      if (++requests === 2) queueMicrotask(() => { pending.delete(source); this.onload?.(); });
    }
    removeAttribute(name) {
      assert.equal(name, "src");
      if (pending.get(this.source) === this) pending.delete(this.source);
    }
    decode() { return Promise.resolve(); }
  };
  const screen = { type: "single_choice", asset: "/v3/retry-test.svg" };
  try {
    await assert.rejects(prepareScreenAssets(screen), /illustration could not load/);
    assert.equal(screenAssetsReady(screen), false);
    await prepareScreenAssets(screen);
    assert.equal(screenAssetsReady(screen), true);
    assert.equal(requests, 2);
  } finally {
    for (const [key, value] of Object.entries(original)) {
      if (value === undefined) delete globalThis[key];
      else globalThis[key] = value;
    }
  }
});
