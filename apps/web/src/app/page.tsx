"use client";
import { useEffect, useRef, useState } from "react";
import { api, type ConsumerBootstrap } from "@/lib/api";
import { campaign } from "@/lib/analytics";
import { Privacy } from "@/components/privacy";
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
  if (bootstrap.restart_required) return <div className="v3-shell"><main className="v3-loading"><h1>Your saved challenge has retired.</h1><p>This version is no longer available. Delete your saved data below to begin the current challenge.</p><Privacy locked={false} /></main></div>;
  return <V3Funnel initial={bootstrap} />;
}
