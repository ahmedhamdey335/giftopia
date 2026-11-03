"use client";

import Link from "next/link";
import { ArrowLeft, Gift, MapPin } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useOrder } from "@/features/orders/use-orders";
import { formatDate, statusTone } from "@/features/orders/status";
import { errorMessage } from "@/lib/api/errors";
import { formatEgp } from "@/lib/money";
import { OrderProgress } from "./order-progress";
import { OrderTimeline } from "./order-timeline";
import { PaymentPanel } from "./payment-panel";
import { StatusBadge } from "./status-badge";

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex justify-between gap-4 text-sm">
      <dt className={strong ? "font-medium" : "text-muted-foreground"}>{label}</dt>
      <dd className={strong ? "font-medium" : undefined}>{value}</dd>
    </div>
  );
}

export function OrderDetail({ id }: { id: string }) {
  const { data: order, error, isLoading, mutate } = useOrder(id);

  const back = (
    <Link href="/orders" className="inline-flex min-h-10 items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
      <ArrowLeft aria-hidden className="size-4" />
      All orders
    </Link>
  );

  if (isLoading) {
    return (
      <div aria-busy className="flex flex-col gap-4">
        {back}
        <div className="h-10 w-1/2 animate-pulse rounded-lg bg-muted" />
        <div className="h-48 animate-pulse rounded-2xl bg-muted" />
      </div>
    );
  }

  if (error || !order) {
    return (
      <div className="flex flex-col gap-4">
        {back}
        <div role="alert" className="flex flex-col items-start gap-3 rounded-2xl border border-border bg-card p-6">
          <p>{errorMessage(error, "We couldn't find this order.")}</p>
          <Button variant="outline" onClick={() => mutate()}>
            Try again
          </Button>
        </div>
      </div>
    );
  }

  const { payment_summary: pay, shipping } = order;
  const needsPayment = statusTone(order.status) === "action" && pay.due_now > 0;

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-col gap-3">
        {back}
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-4xl sm:text-5xl">#{order.order_number}</h1>
          <StatusBadge order={order} />
        </div>
        <p className="text-sm text-muted-foreground">
          Placed {formatDate(order.created_at)}
          {order.status !== "cancelled" && order.status !== "refunded" && <> · Ready by {formatDate(order.ready_by)}</>}
        </p>
      </div>

      <div className="rounded-2xl border border-border bg-card px-3 py-5 sm:px-6">
        <OrderProgress status={order.status} />
        {(order.status === "cancelled" || order.status === "refunded") && (
          <p className="px-2 text-sm text-muted-foreground">This order is {order.status_label.toLowerCase()}.</p>
        )}
      </div>

      <div className="grid gap-8 md:grid-cols-[1.2fr_0.8fr]">
        <div className="flex flex-col gap-8">
          {needsPayment && <PaymentPanel order={order} onCancelled={() => mutate()} />}

          <section aria-labelledby="items-title" className="flex flex-col gap-3">
            <h2 id="items-title" className="text-2xl">
              Your pieces
            </h2>
            <ul className="divide-y divide-border rounded-2xl border border-border bg-card">
              {order.items.map((item) => {
                const details = [
                  item.customization.mood?.name,
                  item.customization.color?.name,
                  item.customization.personalization?.text ? `“${item.customization.personalization.text}”` : null,
                ].filter(Boolean);
                return (
                  <li key={item.id} className="flex items-start justify-between gap-4 p-4">
                    <div className="flex flex-col gap-1">
                      <span className="font-medium">{item.name}</span>
                      {details.length > 0 && <span className="text-sm text-muted-foreground">{details.join(" · ")}</span>}
                    </div>
                    <span className="shrink-0 text-sm">{formatEgp(item.unit_price)}</span>
                  </li>
                );
              })}
            </ul>
          </section>

          <OrderTimeline orderId={id} placedAt={order.created_at} />
        </div>

        <aside className="flex flex-col gap-4">
          <section aria-labelledby="summary-title" className="rounded-2xl border border-border bg-card p-5">
            <h2 id="summary-title" className="mb-4 text-2xl">
              Summary
            </h2>
            <dl className="flex flex-col gap-2">
              <Row label="Subtotal" value={formatEgp(order.subtotal)} />
              <Row label="Delivery" value={formatEgp(order.delivery_fee)} />
              <Row label="Total" value={formatEgp(order.total)} strong />
              <div className="my-2 h-px bg-border" />
              <Row label="Deposit" value={formatEgp(pay.deposit_amount)} />
              <Row label="Balance" value={formatEgp(pay.balance_amount)} />
              <Row label="Paid so far" value={formatEgp(pay.amount_paid)} strong />
            </dl>
          </section>

          <section aria-labelledby="delivery-title" className="rounded-2xl border border-border bg-card p-5 text-sm">
            <h2 id="delivery-title" className="mb-3 flex items-center gap-2 text-2xl">
              <MapPin aria-hidden className="size-5 text-gold" />
              Delivery
            </h2>
            <address className="flex flex-col gap-0.5 not-italic text-muted-foreground">
              <span className="text-foreground">{shipping.recipient_name}</span>
              <span>{shipping.address_line1}</span>
              {shipping.address_line2 && <span>{shipping.address_line2}</span>}
              <span>
                {shipping.city}, {shipping.governorate}
              </span>
              <span>{shipping.phone}</span>
            </address>
          </section>

          {(order.gift_options.gift_wrap || order.gift_options.gift_message) && (
            <section aria-labelledby="gift-title" className="rounded-2xl border border-border bg-card p-5 text-sm">
              <h2 id="gift-title" className="mb-3 flex items-center gap-2 text-2xl">
                <Gift aria-hidden className="size-5 text-gold" />
                Gift
              </h2>
              {order.gift_options.gift_wrap && <p className="text-muted-foreground">Gift wrapped</p>}
              {order.gift_options.gift_message && <p className="mt-1 italic">“{order.gift_options.gift_message}”</p>}
            </section>
          )}
        </aside>
      </div>
    </div>
  );
}
