export type Configuration = {
  landing: { eyebrow: string; title: string; description: string; cta: string };
  steps: [
    {
      id: "memory";
      type: "visual_memory";
      title: string;
      grid_size: number;
      pattern: number[];
      reveal_seconds: number;
    },
  ];
  result: { title: string; disclaimer: string };
};
export type Run = {
  id: string;
  configuration_version: string;
  configuration: Configuration;
  current_step: "memory" | "result";
  completed_steps: string[];
  result: null | {
    kind: "illustrative_demo";
    disclaimer: string;
    indicators: { label: string; value: number }[];
  };
};
export type Bootstrap = {
  configuration_version: string;
  configuration: Configuration;
  run: Run | null;
};
export async function api<T>(path: string, data?: unknown): Promise<T> {
  const token = document.cookie
    .split("; ")
    .find((c) => c.startsWith("XSRF-TOKEN="))
    ?.slice(11);
  const response = await fetch(`/api/v1/${path}`, {
    method: data === undefined ? "GET" : "POST",
    credentials: "same-origin",
    cache: "no-store",
    headers: {
      "Content-Type": "application/json",
      ...(token ? { "X-XSRF-TOKEN": decodeURIComponent(token) } : {}),
    },
    body: data === undefined ? undefined : JSON.stringify(data),
  });
  const json = await response.json();
  if (!response.ok)
    throw new Error(
      json.error?.message ||
        "Something interrupted the round. Please try again.",
    );
  return json as T;
}
