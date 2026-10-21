import type { V3Configuration, V3Screen } from "./v3";

type Priority = "high" | "low";
type CachedImage = { image: HTMLImageElement; ready: boolean; promise: Promise<void> };
// Retain decoded images as well as their promises: a warm HTTP cache alone can still flash.
const images = new Map<string, CachedImage>();
const warming = new Set<string>();

function sources(screen: V3Screen): string[] {
  const commercial = screen.type === "paywall" ? ["/v3/report-hero.svg", "/v3/report-preview.svg"]
    : screen.type === "upsell" ? ["/v3/guide-cover.svg"]
    : screen.type === "final" ? ["/v3/report-hero.svg", ...(screen.guide ? ["/v3/guide-cover.svg"] : [])] : [];
  return [...new Set([screen.asset, screen.art, ...(screen.options || []).map(option => option.asset), ...commercial]
    .filter((source): source is string => !!source && /^\/v3\/[a-z0-9-]+\.svg$/.test(source)))];
}

function load(source: string, priority: Priority): Promise<void> {
  const cached = images.get(source);
  if (cached) {
    if (priority === "high") cached.image.fetchPriority = "high";
    return cached.promise;
  }
  const image = new Image();
  image.decoding = "async";
  image.fetchPriority = priority;
  const entry: CachedImage = { image, ready: false, promise: Promise.resolve() };
  images.set(source, entry);
  entry.promise = new Promise<void>((resolve, reject) => {
    let settled = false;
    const fail = () => {
      if (settled) return;
      settled = true;
      clearTimeout(timeout);
      image.onload = null; image.onerror = null;
      images.delete(source);
      reject(new Error("The next illustration could not load. Check your connection and try again."));
    };
    const timeout = window.setTimeout(fail, 15000);
    image.onerror = fail;
    image.onload = () => {
      const decoded = typeof image.decode === "function" ? image.decode() : Promise.resolve();
      void decoded.then(() => {
        if (settled) return;
        settled = true; clearTimeout(timeout);
        image.onload = null; image.onerror = null;
        entry.ready = true; resolve();
      }, fail);
    };
    image.src = source;
  });
  return entry.promise;
}

export function screenAssetsReady(screen: V3Screen | undefined): boolean {
  return !screen || sources(screen).every(source => images.get(source)?.ready);
}

export async function prepareScreenAssets(screen: V3Screen | undefined): Promise<void> {
  if (screen) await Promise.all(sources(screen).map(source => load(source, "high")));
}

export function warmUpcomingAssets(config: V3Configuration, current: string): void {
  let screen = config.steps.find(step => step.id === current);
  for (let count = 0; screen && count < 4; count++) {
    void prepareScreenAssets(screen).catch(() => {});
    screen = config.steps.find(step => step.id === screen!.next);
  }
}

export function warmFunnelAssets(config: V3Configuration, version: string): void {
  if (warming.has(version)) return;
  warming.add(version);
  const remaining = [...new Set(config.steps.flatMap(sources))];
  // Keep most connections free for the next question and answer persistence.
  const worker = async () => {
    while (remaining.length) {
      const source = remaining.shift()!;
      try { await load(source, "low"); } catch { /* Navigation provides an actionable retry. */ }
    }
  };
  void Promise.all([worker(), worker(), worker()]);
}
