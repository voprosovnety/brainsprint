import type { V3Bootstrap } from "./v3";
export type Deletion = { deleted: true };
export type ConsumerBootstrap = V3Bootstrap;
export async function api<T>(path: string, data?: unknown, signal?: AbortSignal): Promise<T> {
  const controller = new AbortController();
  let timedOut = false;
  const abort = () => controller.abort(signal?.reason);
  if (signal?.aborted) abort();
  else signal?.addEventListener("abort", abort, { once: true });
  // Bound the whole response, including a body that stalls after its headers.
  const timeout = window.setTimeout(() => { timedOut = true; controller.abort(); }, 30000);
  try {
    const token = document.cookie
      .split("; ")
      .find((c) => c.startsWith("XSRF-TOKEN="))
      ?.slice(11);
    const response = await fetch(`/api/v1/${path}`, {
      signal: controller.signal,
      method: data === undefined ? "GET" : "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: {
        "Content-Type": "application/json",
        ...(token ? { "X-XSRF-TOKEN": decodeURIComponent(token) } : {}),
      },
      body: data === undefined ? undefined : JSON.stringify(data),
    });
    const json = await response.json().catch(() => {
      throw new Error("Your challenge could not connect. Please try again; your saved progress is safe.");
    });
    if (!response.ok)
      throw new Error(
        json.error?.message ||
          "Your progress could not be saved. Please try again.",
      );
    return json as T;
  } catch (error) {
    if (timedOut) throw new Error("Your challenge took too long to respond. Please try again; your saved progress is safe.");
    if (error instanceof TypeError) throw new Error("Your challenge could not connect. Please try again; your saved progress is safe.");
    throw error;
  } finally {
    clearTimeout(timeout);
    signal?.removeEventListener("abort", abort);
  }
}
