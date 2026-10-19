export type Tile = { id: string; symbol: string; label: string };
export type ScreenBase = {
  id: string; title: string; description: string; enabled: boolean;
  variant: "standard" | "spotlight"; next: string;
};
export type MemoryScreen = ScreenBase & {
  type: "visual_memory" | "tutorial"; grid_size: number; pattern: number[];
  reveal_seconds: number; branch?: { incorrect: string };
};
export type PatternScreen = ScreenBase & {
  type: "pattern"; sequence: string[]; options: Tile[]; correct_choice: string;
};
export type LogicScreen = ScreenBase & {
  type: "logic"; cards: Tile[]; rules: string[]; correct_order: string[];
};
export type Screen = MemoryScreen | PatternScreen | LogicScreen |
  (ScreenBase & { type: "onboarding" | "result" | "boundary" }) |
  (ScreenBase & { type: "explanation"; grid_size: number; pattern: number[]; reveal_seconds: number });
export type Answer = { cells: number[] } | { choice: string } | { order: string[] } | { acknowledged: true };
export type DisplayConfiguration = {
  schema_version?: 2; entry?: string;
  landing: { eyebrow: string; title: string; description: string; cta: string };
  steps: Screen[];
  result: { title: string; disclaimer: string };
};
type LegacyMemoryScreen = Pick<MemoryScreen, "id" | "title" | "grid_size" | "pattern" | "reveal_seconds"> & { type: "visual_memory" };
export type Configuration = Pick<DisplayConfiguration, "landing" | "result"> & (
  { schema_version: 2; entry: string; steps: Screen[] } |
  { schema_version?: never; entry?: never; steps: LegacyMemoryScreen[] }
);
export function presentConfiguration(config: Configuration): DisplayConfiguration {
  if (config.schema_version === 2) return config;
  return { ...config, steps: config.steps.map(screen => ({ ...screen, enabled: true, variant: "standard", next: "result", description: "Watch the blue cells, then rebuild the pattern. Take your time with your answer." })) };
}
export type Run = {
  id: string; configuration_version: string; configuration: Configuration;
  current_step: string; completed_steps: string[]; answers: Record<string, Answer>;
  demo_completed: boolean;
  result: null | { kind: "illustrative_demo"; disclaimer: string; indicators: { label: string; value: number }[] };
};
export type Bootstrap = { configuration_version: string; configuration: Configuration; run: Run | null };
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
        "Your progress could not be saved. Please try again.",
    );
  return json as T;
}
