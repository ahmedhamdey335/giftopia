"use client";

import Link from "next/link";
import { ChevronRight, Package } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useOrders } from "@/features/orders/use-orders";
import { formatDate, statusTone } from "@/features/orders/status";
import { errorMessage } from "@/lib/api/errors";
import { formatEgp } from "@/lib/money";
import { StatusBadge } from "./status-badge";

export function OrderList() {
  const { data, error, isLoading, mutate } = useOrders();

  if (isLoading) {
    return (
      <ul aria-busy className="flex flex-col gap-3">
        {[0, 1, 2].map((key) => (
          <li key={key} className="h-28 animate-pulse rounded-2xl bg-muted" />
        ))}
      </ul>
    );
  }

  if (error) {
    return (
      <div role="alert" className="flex flex-col items-start gap-3 rounded-2xl border border-border bg-card p-6">
        <p>{errorMessage(error, "We couldn't load your orders.")}</p>
        <Button variant="outline" onClick={() => mutate()}>
          Try again
        </Button>
      </div>
    );
  }

  const orders = data?.items ?? [];

  if (orders.length === 0) {
    return (
      <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border bg-card px-6 py-14 text-center">
        <span className="flex size-14 items-center justify-center rounded-full bg-accent">
          <Package aria-hidden className="size-6 text-primary" />
        </span>
        <div className="flex flex-col gap-1">
          <h2 className="text-2xl">No orders yet</h2>
          <p className="text-sm text-muted-foreground">Your first keepsake is a few choices away.</p>
        </div>
        <Button asChild size="lg">
          <Link href="/studio">Start designing</Link>
        </Button>
      </div>
    );
  }

  return (
    <ul className="flex flex-col gap-3">
      {orders.map((order) => {
        const needsAction = statusTone(order.status) === "action";
        return (
          <li key={order.id}>
            <Link
              href={`/orders/${order.id}`}
              className="flex items-center gap-4 rounded-2xl border border-border bg-card p-4 transition-shadow hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:p-5"
            >
              <div className="flex min-w-0 flex-1 flex-col gap-2">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-medium">#{order.order_number}</span>
                  <StatusBadge order={order} />
                </div>
                <p className="truncate text-sm text-muted-foreground">
                  {order.items.map((item) => item.name).join(", ")}
                </p>
                <p className="text-xs text-muted-foreground">
                  Placed {formatDate(order.created_at)} · Ready by {formatDate(order.ready_by)}
                </p>
              </div>
              <div className="flex shrink-0 flex-col items-end gap-1 text-end">
                <span className="font-medium">{formatEgp(order.total)}</span>
                {needsAction && order.payment_summary.due_now > 0 && (
                  <span className="text-xs font-medium text-bronze">
                    {formatEgp(order.payment_summary.due_now)} due
                  </span>
                )}
              </div>
              <ChevronRight aria-hidden className="size-5 shrink-0 text-muted-foreground" />
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
