import type { Order } from "@/features/orders/orders-api";
import { statusTone } from "@/features/orders/status";
import { cn } from "@/lib/utils";

const toneClasses = {
  action: "border-primary/30 bg-primary text-primary-foreground",
  progress: "border-gold/50 bg-accent text-accent-foreground",
  done: "border-border bg-secondary text-secondary-foreground",
  closed: "border-border bg-muted text-muted-foreground",
};

export function StatusBadge({ order }: { order: Pick<Order, "status" | "status_label"> }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium",
        toneClasses[statusTone(order.status)],
      )}
    >
      {order.status_label}
    </span>
  );
}
