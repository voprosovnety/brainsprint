export type Rate = { numerator: number; denominator: number; rate: number | null; from?: string };
export type DemoAnalytics = {
  dataset: "synthetic";
  v3: { runs: number; totals: Record<string, number>; stage_conversions: Record<string, Rate>; quiz_elapsed_seconds: {count: number; mean: number | null} };
  cohort: { from: string; to_exclusive: string; as_of: string; observation_hours: number };
  totals: Record<string, number>;
  stage_conversions: Record<string, Rate>;
  quiz_elapsed_seconds: { count: number; mean: number | null };
  experiment: { id: string; status: "insufficient_data"; winner: null; variants: Record<"A" | "B", {
    exposed: number; eligible_exposed: number; pending_observation: number;
    start_conversion: Rate; quiz_conversion: Rate; boundary_conversion: Rate;
  }> };
};
export function formatRate(rate: Rate) { return rate.rate === null ? "—" : `${Math.round(rate.rate * 100)}%`; }
