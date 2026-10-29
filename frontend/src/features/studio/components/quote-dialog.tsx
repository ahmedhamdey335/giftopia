"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { formatEgp } from "@/lib/money";
import type { QuoteSummary } from "../types";

export function QuoteDialog({ quote, onClose }: { quote: QuoteSummary; onClose: () => void }) {
  const dialogRef = useRef<HTMLDivElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);
  const [now, setNow] = useState(() => Date.now());
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const secondsLeft = Math.max(0, Math.ceil((new Date(quote.expires_at).getTime() - now) / 1000));

  useEffect(() => {
    previousFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    closeRef.current?.focus();
    const timer = window.setInterval(() => setNow(Date.now()), 1000);
    return () => {
      window.clearInterval(timer);
      previousFocusRef.current?.focus();
    };
  }, []);

  const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (event.key === "Escape") {
      event.preventDefault();
      onClose();
      return;
    }
    if (event.key !== "Tab") return;
    const focusable = dialogRef.current?.querySelectorAll<HTMLElement>(
      'button:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );
    if (!focusable?.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onMouseDown={(event) => {
      if (event.target === event.currentTarget) onClose();
    }}>
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="quote-dialog-title"
        aria-describedby="quote-dialog-description"
        tabIndex={-1}
        onKeyDown={handleKeyDown}
        className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-border bg-background p-6 shadow-xl"
      >
        <h2 id="quote-dialog-title" className="text-2xl font-display">Your quote is ready</h2>
        <p id="quote-dialog-description" className="mt-2 text-sm text-muted-foreground">
          This server-priced quote is held for a limited time. Your production date is indicative until the deposit is confirmed.
        </p>
        <p className="mt-4 text-3xl font-display">{formatEgp(quote.total)}</p>
        <ul className="mt-4 divide-y divide-border rounded-lg border border-border">
          {quote.price_breakdown.items.map((item) => (
            <li key={`${item.key}-${item.id ?? "none"}`} className="flex justify-between gap-4 p-3 text-sm">
              <span>{item.name}</span>
              <span className="shrink-0">{formatEgp(item.amount)}</span>
            </li>
          ))}
        </ul>
        <p className="mt-4 text-sm">
          Indicative ready date: {new Date(quote.ready_at).toLocaleDateString()}
        </p>
        <p role="timer" className={`mt-2 text-sm ${secondsLeft === 0 ? "text-destructive" : "text-muted-foreground"}`}>
          {secondsLeft === 0
            ? "This quote has expired. Request a new quote to continue."
            : `Quote expires in ${String(Math.floor(secondsLeft / 60)).padStart(2, "0")}:${String(secondsLeft % 60).padStart(2, "0")}`}
        </p>
        <div className="mt-6 flex justify-end">
          <Button ref={closeRef} type="button" variant="outline" onClick={onClose}>Close quote</Button>
          {secondsLeft > 0 && (
            <Button asChild className="ms-2">
              <Link href="/checkout">Continue to checkout</Link>
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
