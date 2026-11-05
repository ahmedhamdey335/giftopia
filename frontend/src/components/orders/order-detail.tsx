"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { ArrowLeft, Ban, Gift, MapPin, RotateCcw } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { formatDate, statusTone } from "@/features/orders/status";
import { ordersApi } from "@/features/orders/orders-api";
import { useOrder } from "@/features/orders/use-orders";
import { draftToSelections } from "@/features/studio/store/mappers";
import { useStudioStore } from "@/features/studio/store/studio-store";
import { errorMessage, isApiError } from "@/lib/api/errors";
import { formatEgp } from "@/lib/money";
import { OrderProgress } from "./order-progress";
import { OrderTimeline } from "./order-timeline";
import { PaymentPanel } from "./payment-panel";
import { ProofCard } from "./proof-card";
import { RefundSection } from "./refund-dialog";
import { StatusBadge } from "./status-badge";

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex justify-between gap-4 text-sm">
      <dt className={strong ? "font-medium text-foreground" : "text-muted-foreground"}>{label}</dt>
      <dd className={strong ? "font-medium text-foreground" : undefined}>{value}</dd>
    </div>
  );
}

function PaymentReturnWatcher({ onTick }: { onTick: () => void }) {
  const searchParams = useSearchParams();
  const isReturn = searchParams.get("payment") === "return";

  useEffect(() => {
    if (!isReturn) return;
    let count = 0;
    const interval = window.setInterval(() => {
      count++;
      onTick();
      if (count >= 6) {
        window.clearInterval(interval);
      }
    }, 2500);
    return () => window.clearInterval(interval);
  }, [isReturn, onTick]);

  return null;
}

export function OrderDetail({ id }: { id: string }) {
  const router = useRouter();
  const { data: order, error, isLoading, mutate } = useOrder(id);
  const [isCancelling, setIsCancelling] = useState(false);
  const [isReordering, setIsReordering] = useState(false);

  const back = (
    <Link
      href="/orders"
      className="inline-flex min-h-10 items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
    >
      <ArrowLeft aria-hidden className="size-4" />
      All orders
    </Link>
  );

  if (isLoading) {
    return (
      <div aria-busy className="flex flex-col gap-6">
        {back}
        <div className="h-10 w-1/3 animate-pulse rounded-xl bg-muted" />
        <div className="h-32 animate-pulse rounded-2xl bg-muted" />
        <div className="grid gap-6 md:grid-cols-[1.2fr_0.8fr]">
          <div className="h-64 animate-pulse rounded-2xl bg-muted" />
          <div className="h-64 animate-pulse rounded-2xl bg-muted" />
        </div>
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
  const canCancel = order.status === "awaiting_deposit";
  const canReorder = order.status !== "awaiting_deposit" && order.status !== "cancelled";

  async function handleCancel() {
    if (!order) return;
    if (!confirm("Are you sure you want to cancel this order? This cannot be undone.")) return;
    setIsCancelling(true);
    try {
      await ordersApi.cancel(order.id);
      toast.success("Order cancelled.");
      await mutate();
    } catch (err) {
      toast.error(errorMessage(err, "Failed to cancel order."));
    } finally {
      setIsCancelling(false);
    }
  }

  async function handleReorder() {
    if (!order) return;
    setIsReordering(true);
    try {
      const res = await ordersApi.reorder(order.id);

      useStudioStore.getState().setQuote(res.quote);
      toast.success("Reorder design quoted! Proceed to checkout.");
      router.push("/checkout");
    } catch (err: unknown) {
      if (isApiError(err) && err.status === 422) {
        const errorRecord = err.errors as Record<string, unknown> | undefined;
        const draftData = errorRecord?.draft;
        const parsed = draftToSelections(draftData);
        if (parsed) {
          useStudioStore.getState().loadSelections(parsed.selections, 1);
          toast.info(
            "Some previous materials have changed. We loaded your design into the Studio so you can update them.",
          );
          router.push("/studio");
          return;
        }
      }
      toast.error(errorMessage(err, "Could not reorder this design."));
    } finally {
      setIsReordering(false);
    }
  }

  return (
    <div className="flex flex-col gap-8">
      <Suspense fallback={null}>
        <PaymentReturnWatcher onTick={() => void mutate()} />
      </Suspense>

      <div className="flex flex-col gap-3">
        {back}
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-4xl sm:text-5xl">#{order.order_number}</h1>
            <StatusBadge order={order} />
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {canReorder && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleReorder}
                disabled={isReordering}
              >
                <RotateCcw className="size-4" />
                {isReordering ? "Quoting…" : "Reorder this piece"}
              </Button>
            )}
            {canCancel && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleCancel}
                disabled={isCancelling}
                className="text-destructive hover:bg-destructive/10"
              >
                <Ban className="size-4" />
                {isCancelling ? "Cancelling…" : "Cancel order"}
              </Button>
            )}
          </div>
        </div>
        <p className="text-sm text-muted-foreground">
          Placed {formatDate(order.created_at)}
          {order.status !== "cancelled" && order.status !== "refunded" && <> · Ready by {formatDate(order.ready_by)}</>}
        </p>
      </div>

      <div className="rounded-2xl border border-border bg-card px-3 py-5 sm:px-6">
        <OrderProgress status={order.status} />
        {(order.status === "cancelled" || order.status === "refunded") && (
          <p className="mt-3 px-2 text-sm text-muted-foreground">This order is {order.status_label.toLowerCase()}.</p>
        )}
      </div>

      <div className="grid gap-8 md:grid-cols-[1.2fr_0.8fr]">
        <div className="flex flex-col gap-8">
          {needsPayment && <PaymentPanel order={order} onCancelled={() => void mutate()} />}

          <ProofCard orderId={order.id} onUpdated={() => void mutate()} />

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
                      <span className="font-medium text-foreground">{item.name}</span>
                      {details.length > 0 && (
                        <span className="text-sm text-muted-foreground">{details.join(" · ")}</span>
                      )}
                    </div>
                    <span className="shrink-0 text-sm">{formatEgp(item.unit_price)}</span>
                  </li>
                );
              })}
            </ul>
          </section>

          <OrderTimeline orderId={id} placedAt={order.created_at} />

          <RefundSection order={order} onUpdated={() => void mutate()} />
        </div>

        <aside className="flex flex-col gap-4">
          <section aria-labelledby="summary-title" className="rounded-2xl border border-border bg-card p-5">
            <h2 id="summary-title" className="mb-4 text-2xl font-serif">
              Summary
            </h2>
            <dl className="flex flex-col gap-2">
              <Row label="Subtotal" value={formatEgp(order.subtotal)} />
              <Row label="Delivery" value={formatEgp(order.delivery_fee)} />
              <Row label="Total" value={formatEgp(order.total)} strong />
              <div className="my-2 h-px bg-border" />
              <Row label="Deposit (50%)" value={formatEgp(pay.deposit_amount)} />
              <Row label="Balance" value={formatEgp(pay.balance_amount)} />
              <Row label="Paid so far" value={formatEgp(pay.amount_paid)} strong />
            </dl>
          </section>

          <section aria-labelledby="delivery-title" className="rounded-2xl border border-border bg-card p-5 text-sm">
            <h2 id="delivery-title" className="mb-3 flex items-center gap-2 text-2xl font-serif">
              <MapPin aria-hidden className="size-5 text-gold" />
              Delivery
            </h2>
            <address className="flex flex-col gap-0.5 not-italic text-muted-foreground">
              <span className="font-medium text-foreground">{shipping.recipient_name}</span>
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
              <h2 id="gift-title" className="mb-3 flex items-center gap-2 text-2xl font-serif">
                <Gift aria-hidden className="size-5 text-gold" />
                Gift packaging
              </h2>
              {order.gift_options.gift_wrap && <p className="text-muted-foreground">Artisanal gift wrapping included</p>}
              {order.gift_options.gift_message && (
                <p className="mt-1 italic text-foreground">“{order.gift_options.gift_message}”</p>
              )}
            </section>
          )}
        </aside>
      </div>
    </div>
  );
}
