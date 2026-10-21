export type Rate = { numerator: number; denominator: number; rate: number | null; from?: string };
export type DemoAnalytics = {
  dataset: "synthetic";
  cohort: { from: string; to_exclusive: string; as_of: string; observation_hours: number };
  totals: Record<string, number>;
  stage_conversions: Record<string, Rate>;
  quiz_elapsed_seconds: { count: number; mean: number | null };

};
export function formatRate(rate: Rate) { return rate.rate === null ? "—" : `${Math.round(rate.rate * 100)}%`; }
