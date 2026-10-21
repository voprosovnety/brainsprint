export type V3Option = { id: string; label: string; asset?: string };
export type V3Screen = {
  id: string; type: "word_intro" | "single_choice" | "scale" | "timed_reveal" | "interstitial" | "confirmation" | "processing" | "email" | "paywall" | "upsell" | "final";
  title: string; description: string; enabled: boolean; variant: "standard" | "spotlight"; next: string;
  options?: V3Option[]; presentation?: "image" | "objects"; asset?: string; asset_alt?: string;
  word?: string; digits?: string; reveal_seconds?: number; role?: "demographic"; cta?: string;
  art?: string; prompts?: { id: "focus" | "practice"; title: string }[]; stages?: string[];
  duration_seconds?: number; sample_email?: string; offer_id?: string; decline_next?: string; guide?: boolean;
};
export type V3Answer = { choice: string } | { acknowledged: true } | { focus: "yes" | "no"; practice: "yes" | "no" } | { email: string | null } | { offer_id: string; acknowledged: true } | { decision: "accept" | "decline" };
export type V3Configuration = {
  schema_version: 3; entry: string;
  result: { title: string; disclaimer: string };
  offer: { id: string; comparison_cents: number; intro_cents: number; intro_days: number; renewal_cents: number; renewal_weeks: number; countdown_seconds: number; guide_id: string; guide_comparison_cents: number; guide_cents: number };
  steps: V3Screen[];
};
export type V3Report = {
  kind: "illustrative_sample"; title: string; summary: string; disclaimer: string;
  sections: { title: string; body: string; practice: string }[];
};
export type V3Run = {
  id: string; configuration_version: string; configuration: V3Configuration; current_step: string;
  completed_steps: string[]; answers: Record<string, V3Answer>; demo_completed: boolean;
  started_at: string;
  teaser: null | { title: string; summary: string; sections: string[] };
  result: V3Report | null;
  commerce: { deadline: string | null; unlocked: boolean; upsell: "accept" | "decline" | null };
};
export type V3Bootstrap = { experiment: null; restart_required: boolean; configuration_version: string; configuration: V3Configuration; run: V3Run | null };
