import type { OrderStatus } from "./orders-api";

export const progressSteps = ["Deposit", "Being made", "Balance", "On its way", "Delivered"] as const;

/** Index of the current step in `progressSteps`, or -1 when the order left the normal flow. */
export function progressIndex(status: OrderStatus): number {
  switch (status) {
    case "awaiting_deposit":
      return 0;
    case "confirmed":
      return 1;
    case "awaiting_balance":
      return 2;
    case "ready_for_delivery":
    case "out_for_delivery":
      return 3;
    case "delivered":
      return 4;
    default:
      return -1;
  }
}

export type StatusTone = "action" | "progress" | "done" | "closed";

export function statusTone(status: OrderStatus): StatusTone {
  if (status === "awaiting_deposit" || status === "awaiting_balance") return "action";
  if (status === "delivered") return "done";
  if (status === "cancelled" || status === "refunded") return "closed";
  return "progress";
}

export function formatDate(iso: string, withTime = false): string {
  return new Intl.DateTimeFormat("en-GB", {
    day: "numeric",
    month: "short",
    year: withTime ? undefined : "numeric",
    hour: withTime ? "numeric" : undefined,
    minute: withTime ? "2-digit" : undefined,
  }).format(new Date(iso));
}
