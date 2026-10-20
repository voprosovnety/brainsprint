"use client";
import { useEffect, useRef, useState } from "react";
import { api, type Deletion } from "@/lib/api";
import { pauseCollection, resumeCollection } from "@/lib/analytics";

export function Privacy({ locked }: { locked: boolean }) {
  const deleteAction = useRef<HTMLButtonElement>(null);
  const cancelAction = useRef<HTMLButtonElement>(null);
  const [confirm, setConfirm] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  useEffect(() => { if (confirm) cancelAction.current?.focus(); }, [confirm]);
  async function remove() {
    setBusy(true); setError("");
    try {
      await pauseCollection();
      await api<Deletion>("privacy/delete", {});
      try { sessionStorage.setItem("brainsprint-data-deleted", "1"); } catch {}
      window.location.replace("/");
    } catch { resumeCollection(); setError("Deletion could not be confirmed. Reconnect and try again, or reload to check your saved state."); setBusy(false); requestAnimationFrame(() => cancelAction.current?.focus()); }
  }
  return <details className="privacy-note"><summary>About this demo &amp; your data</summary>
    <div className="privacy-body"><h2>A little play, with a clear boundary.</h2>
      <p>BrainSprint is an entertainment demo. Snapshot values are illustrative, saved once and unrelated to your answers. They do not measure intelligence, health or cognitive ability. There are no payments or subscriptions.</p>
      <p>A cookie keeps anonymous progress, answers and your snapshot available in this browser for up to seven days of inactivity. We store a random visitor reference, a stable landing variant, first-visit campaign tags (UTM) and events for screens viewed and steps completed. Session records also contain technical IP and browser information; analytics does not use these to identify you.</p>
      <p>Anonymous progress and events, including landing views and labeled synthetic samples, become eligible for deletion 30 days after their last stored activity. Expired sessions are removed separately. Maintenance requires an operator run; automatic scheduling is not configured. Backups may retain older data until their own expiry. Public demo analytics shows synthetic samples only.</p>
      <p>You can permanently delete the saved progress and events linked to this browser’s current cookie. Other browsers and data whose cookies you cleared cannot be recovered or linked here. After deletion, returning to the demo begins a new anonymous visit and records new activity.</p>
      {!confirm ? <button ref={deleteAction} className="text-button" disabled={locked || busy} onClick={() => setConfirm(true)}>Delete my saved data</button> : <div className="privacy-confirm"><p id="delete-warning"><strong>Delete your saved progress, snapshot and events?</strong> This cannot be undone.</p><div className="error-actions"><button className="button" disabled={locked || busy} aria-describedby="delete-warning" onClick={remove}>{busy ? "Deleting…" : "Permanently delete my data"}</button><button ref={cancelAction} className="text-button" disabled={busy} onClick={() => { setConfirm(false); setError(""); requestAnimationFrame(() => deleteAction.current?.focus()); }}>Keep my data</button></div></div>}
      {error && <p role="alert">{error}</p>}
    </div>
  </details>;
}
