import { Check } from "lucide-react";
import type { OrderStatus } from "@/features/orders/orders-api";
import { progressIndex, progressSteps } from "@/features/orders/status";
import { cn } from "@/lib/utils";

export function OrderProgress({ status }: { status: OrderStatus }) {
  const current = progressIndex(status);
  if (current < 0) return null;

  return (
    <ol aria-label="Order progress" className="flex items-start">
      {progressSteps.map((label, index) => {
        const done = index < current || status === "delivered";
        const active = index === current && status !== "delivered";
        return (
          <li
            key={label}
            aria-current={active ? "step" : undefined}
            className="relative flex flex-1 flex-col items-center gap-2 text-center"
          >
            {index > 0 && (
              <span
                aria-hidden
                className={cn(
                  "absolute end-1/2 top-3.5 h-0.5 w-full -translate-y-1/2",
                  index <= current ? "bg-primary" : "bg-border",
                )}
              />
            )}
            <span
              className={cn(
                "relative z-10 flex size-7 items-center justify-center rounded-full border-2 text-xs font-medium",
                done && "border-primary bg-primary text-primary-foreground",
                active && "border-primary bg-background text-primary ring-4 ring-primary/15",
                !done && !active && "border-border bg-background text-muted-foreground",
              )}
            >
              {done ? <Check aria-hidden className="size-3.5" /> : index + 1}
            </span>
            <span
              className={cn(
                "text-[11px] leading-tight sm:text-xs",
                active ? "font-medium text-foreground" : "text-muted-foreground",
              )}
            >
              {label}
              {done && <span className="sr-only"> (complete)</span>}
            </span>
          </li>
        );
      })}
    </ol>
  );
}
