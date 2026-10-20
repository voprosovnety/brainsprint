"use client";
import { useEffect, useRef, useState } from "react";
import { api, type Bootstrap, type Configuration, type ConsumerBootstrap } from "@/lib/api";
import { campaign } from "@/lib/analytics";
import type { V3Bootstrap } from "@/lib/v3";
import { LegacyFunnel } from "@/components/legacy-funnel";
import { V3Funnel } from "@/components/v3-funnel";

export default function Home() {
  const [bootstrap, setBootstrap] = useState<ConsumerBootstrap | null>(null);
  const [error, setError] = useState("");
  const loading = useRef(false);
  async function load() {
    if (loading.current) return;
    loading.current = true; setError("");
    try {
      const query = new URLSearchParams(campaign()).toString();
      setBootstrap(await api<ConsumerBootstrap>(`bootstrap${query ? `?${query}` : ""}`));
    } catch (e) { setError((e as Error).message || "Your challenge could not open. Please try again."); }
    finally { loading.current = false; }
  }
  useEffect(() => { void load(); }, []);
  if (!bootstrap) return <div className="v3-shell"><main className="v3-loading"><span className="v3-spark" aria-hidden="true">✳</span><h1>Make a little room for curiosity.</h1>{error ? <><p role="alert">{error}</p><button className="v3-primary" onClick={load}>Try again</button></> : <p role="status">Opening your challenge…</p>}</main></div>;
  // An owned historical run always wins over the latest published version.
  const configuration = bootstrap.run?.configuration || bootstrap.configuration;
  if (configuration.schema_version === 3) return <V3Funnel initial={bootstrap as V3Bootstrap} />;
  const legacy = bootstrap as Bootstrap;
  return <LegacyFunnel initial={{ ...legacy, configuration: configuration as Configuration, experiment: legacy.experiment || { id: "landing-curiosity-v1", variant: "A" } }} />;
}
