"use client";
import { useEffect, useRef, useState } from "react";
import { api, type Deletion } from "@/lib/api";
import { pauseCollection, resumeCollection } from "@/lib/analytics";
import { clearAnswerQueue } from "@/lib/v3-answer-queue";

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
      clearAnswerQueue();
      try { sessionStorage.setItem("brainsprint-data-deleted", "1"); } catch {}
      window.location.replace("/");
    } catch { resumeCollection(); setError("Deletion could not be confirmed. Reconnect and try again, or reload to check your saved state."); setBusy(false); requestAnimationFrame(() => cancelAction.current?.focus()); }
  }
  return <details className="privacy-note"><summary>About this demo &amp; your data</summary>
    <div className="privacy-body"><h2>About your experience.</h2>
      <p>BrainSprint is an entertainment demo. Reports and social examples are original illustrative samples, independent of answer correctness. They do not measure intelligence, health or cognitive ability. Prices, checkout and guide offers are simulated: no payments, subscriptions or charges occur.</p>
      <p>A cookie keeps anonymous progress, answers and your report and simulated offer choices available in this browser for up to seven days of inactivity. We store a random visitor reference, first-visit campaign tags (UTM) and events for screens viewed and steps completed. Session records also contain technical IP and browser information; analytics does not use these to identify you.</p>
      <p>Quiz answers waiting to save are also kept in this tab's session storage so reloading can resume them. This temporary queue contains no email, report, payment details or session credentials. It is cleared after confirmation or deletion; closing the tab may remove unsent answers.</p>
      <p>Anonymous progress and events, including labeled synthetic samples, become eligible for deletion 30 days after their last stored activity. Expired sessions are removed separately. Scheduled maintenance applies the retention policy. Backups may retain older data until their own expiry. Public demo analytics shows synthetic samples only.</p>
      <p>The optional email field is saved only with your private answers. No email is sent, no account is created and no marketing subscription is added. Email never enters screen events or public analytics. Checkout uses fixed placeholders and collects no financial information.</p>
      <p>You can permanently delete the saved progress and events linked to this browser’s current cookie. Other browsers and data whose cookies you cleared cannot be recovered or linked here. After deletion, returning to the demo begins a new anonymous visit and records new activity.</p>
      {!confirm ? <button ref={deleteAction} className="text-button" disabled={locked || busy} onClick={() => setConfirm(true)}>Delete my saved data</button> : <div className="privacy-confirm"><p id="delete-warning"><strong>Delete your saved progress, report, offer choices and events?</strong> This cannot be undone.</p><div className="error-actions"><button className="button" disabled={locked || busy} aria-describedby="delete-warning" onClick={remove}>{busy ? "Deleting…" : "Permanently delete my data"}</button><button ref={cancelAction} className="text-button" disabled={busy} onClick={() => { setConfirm(false); setError(""); requestAnimationFrame(() => deleteAction.current?.focus()); }}>Keep my data</button></div></div>}
      {error && <p role="alert">{error}</p>}
    </div>
  </details>;
}
