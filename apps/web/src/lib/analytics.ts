import { api } from "./api";

export function campaign() {
  const params = new URLSearchParams(window.location.search);
  return Object.fromEntries(["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"]
    .flatMap(key => params.has(key) ? [[key, params.get(key)!]] : []));
}

type ViewEvent = {
  name: "step_viewed" | "paywall_viewed_v3" | "checkout_viewed_v3" | "report_viewed_v3";
  run_id?: string; step_id?: string;
};
// Small in-memory outbox; no URLs, cookies, answers or persistent browser identifiers.
// Each delivery has a deadline and two retries, always reusing the original ID.
let paused = false;
export async function pauseCollection() { paused = true; await Promise.all([...pending.values()]); }
export function resumeCollection() { paused = false; }
const pending = new Map<string, Promise<void>>();
export function recordView(event: ViewEvent): Promise<void> {
  if (paused) return Promise.resolve();
  const key = `${event.name}:${event.run_id || ""}:${event.step_id || ""}`;
  const existing = pending.get(key);
  if (existing) return existing;
  const payload = { ...event, event_id: crypto.randomUUID(), properties: { surface: "page" } };
  const delivery = (async () => {
    for (let attempt = 0; attempt < 3; attempt++) {
      if (paused) return;
      try {
        await api("events", payload, AbortSignal.timeout(1500));
        return;
      } catch {
        if (attempt < 2) await new Promise(resolve => setTimeout(resolve, 800 * (attempt + 1)));
      }
    }
  })().finally(() => pending.delete(key));
  pending.set(key, delivery);
  return delivery;
}
