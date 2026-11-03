"use client";

import { useOrderTimeline } from "@/features/orders/use-orders";
import { formatDate } from "@/features/orders/status";

export function OrderTimeline({ orderId, placedAt }: { orderId: string; placedAt: string }) {
  const { data, isLoading } = useOrderTimeline(orderId);
  const events = [{ type: "placed", label: "Order placed", created_at: placedAt }, ...(data?.events ?? [])];

  return (
    <section aria-labelledby="timeline-title" className="flex flex-col gap-4">
      <h2 id="timeline-title" className="text-2xl">
        Activity
      </h2>
      <ol aria-busy={isLoading} className="flex flex-col">
        {[...events].reverse().map((event, index) => (
          <li key={`${event.type}-${event.created_at}`} className="relative flex gap-4 pb-5 last:pb-0">
            {index < events.length - 1 && (
              <span aria-hidden className="absolute start-[5px] top-4 h-full w-px bg-border" />
            )}
            <span
              aria-hidden
              className={index === 0 ? "mt-1.5 size-[11px] shrink-0 rounded-full bg-primary" : "mt-1.5 size-[11px] shrink-0 rounded-full border-2 border-border bg-background"}
            />
            <div className="flex flex-col">
              <span className={index === 0 ? "font-medium" : "text-muted-foreground"}>{event.label}</span>
              <time dateTime={event.created_at} className="text-xs text-muted-foreground">
                {formatDate(event.created_at, true)}
              </time>
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}
