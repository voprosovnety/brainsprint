import { api } from "./api";

export function campaign() {
  const params = new URLSearchParams(window.location.search);
  return Object.fromEntries(["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"]
    .flatMap(key => params.has(key) ? [[key, params.get(key)!]] : []));
}

type ViewEvent = {
  name: "landing_viewed" | "step_viewed" | "result_viewed" | "paywall_reached";
  run_id?: string; step_id?: string;
};
// Small in-memory outbox; no URLs, cookies, answers or persistent browser identifiers.
// Each delivery has a deadline and two retries, always reusing the original ID.
const pending = new Map<string, Promise<void>>();
export function recordView(event: ViewEvent): Promise<void> {
  const key = `${event.name}:${event.run_id || ""}:${event.step_id || ""}`;
  const existing = pending.get(key);
  if (existing) return existing;
  const payload = { ...event, event_id: crypto.randomUUID(), properties: { surface: "page" } };
  const delivery = (async () => {
    for (let attempt = 0; attempt < 3; attempt++) {
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

export async function beforeStart() {
  const landing = pending.get("landing_viewed::");
  // Give the already-rendered exposure an opportunity to arrive before Start.
  // Failed analytics can delay Start by at most 1.6 seconds, never freeze it.
  if (landing) await Promise.race([landing, new Promise(resolve => setTimeout(resolve, 1600))]);
}
