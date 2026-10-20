"use client";

import { useEffect, useState } from "react";

export function WaitingNotice({ active }: { active: boolean }) {
  const [slow, setSlow] = useState(false);
  useEffect(() => {
    setSlow(false);
    if (!active) return;
    const timer = setTimeout(() => setSlow(true), 8000);
    return () => clearTimeout(timer);
  }, [active]);

  return active && slow ? <p role="status">After a break, this demo can take about a minute to wake up. Your saved progress is safe.</p> : null;
}
