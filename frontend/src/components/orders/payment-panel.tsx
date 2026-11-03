"use client";

import { useState } from "react";
import { CreditCard, Smartphone } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ordersApi, type Order, type PaymentMethod } from "@/features/orders/orders-api";
import { errorMessage } from "@/lib/api/errors";
import { formatEgp } from "@/lib/money";
import { cn } from "@/lib/utils";

const methods: { value: PaymentMethod; label: string; icon: typeof CreditCard }[] = [
  { value: "card", label: "Card", icon: CreditCard },
  { value: "wallet", label: "Mobile wallet", icon: Smartphone },
];

export function PaymentPanel({ order, onCancelled }: { order: Order; onCancelled: () => void }) {
  const kind = order.status === "awaiting_deposit" ? "deposit" : "balance";
  const [method, setMethod] = useState<PaymentMethod>(order.payment_method);
  const [pending, setPending] = useState<"pay" | "cancel" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [confirmCancel, setConfirmCancel] = useState(false);

  async function pay() {
    setPending("pay");
    setError(null);
    try {
      const attempt = await ordersApi.pay(order.id, kind, method);
      window.location.assign(attempt.redirect_url);
    } catch (err) {
      setError(errorMessage(err, "We couldn't start the payment."));
      setPending(null);
    }
  }

  async function cancel() {
    setPending("cancel");
    setError(null);
    try {
      await ordersApi.cancel(order.id);
      onCancelled();
    } catch (err) {
      setError(errorMessage(err, "We couldn't cancel this order."));
    } finally {
      setPending(null);
      setConfirmCancel(false);
    }
  }

  return (
    <section aria-labelledby="pay-title" className="flex flex-col gap-4 rounded-2xl border border-primary/30 bg-card p-5">
      <div className="flex flex-col gap-1">
        <h2 id="pay-title" className="text-2xl">
          {kind === "deposit" ? "Pay your deposit to start" : "Pay the balance to ship"}
        </h2>
        <p className="text-sm text-muted-foreground">
          {kind === "deposit"
            ? "We reserve your production slot as soon as the deposit is confirmed."
            : "Your piece is ready. Once the balance is paid, we send it out for delivery."}
        </p>
      </div>

      <fieldset className="flex flex-col gap-2">
        <legend className="mb-2 text-sm font-medium">Payment method</legend>
        <div className="grid grid-cols-2 gap-2">
          {methods.map(({ value, label, icon: Icon }) => (
            <label
              key={value}
              className={cn(
                "flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border px-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring",
                method === value ? "border-primary bg-accent font-medium" : "border-border hover:bg-secondary",
              )}
            >
              <input
                type="radio"
                name="method"
                value={value}
                checked={method === value}
                onChange={() => setMethod(value)}
                className="sr-only"
              />
              <Icon aria-hidden className="size-4" />
              {label}
            </label>
          ))}
        </div>
      </fieldset>

      {error && (
        <p role="alert" className="text-sm text-destructive">
          {error}
        </p>
      )}

      <Button size="lg" onClick={pay} disabled={pending !== null}>
        {pending === "pay" ? "Redirecting…" : `Pay ${formatEgp(order.payment_summary.due_now)}`}
      </Button>

      {kind === "deposit" &&
        (confirmCancel ? (
          <div className="flex flex-col gap-2 rounded-xl bg-muted p-3 text-sm">
            <p>Cancel this order? This can&apos;t be undone.</p>
            <div className="flex gap-2">
              <Button variant="destructive" size="sm" onClick={cancel} disabled={pending !== null}>
                {pending === "cancel" ? "Cancelling…" : "Yes, cancel"}
              </Button>
              <Button variant="ghost" size="sm" onClick={() => setConfirmCancel(false)}>
                Keep order
              </Button>
            </div>
          </div>
        ) : (
          <Button variant="ghost" size="sm" className="self-center" onClick={() => setConfirmCancel(true)}>
            Cancel order
          </Button>
        ))}
    </section>
  );
}
