import { apiFetch, newIdempotencyKey } from "@/lib/api/client";

export type OrderStatus =
  | "awaiting_deposit"
  | "confirmed"
  | "awaiting_balance"
  | "ready_for_delivery"
  | "out_for_delivery"
  | "delivered"
  | "cancelled"
  | "refunded";

export type PaymentMethod = "card" | "wallet";

export interface OrderItem {
  id: number;
  name: string;
  unit_price: number;
  proof_status: string;
  customization: {
    mood?: { name?: string };
    color?: { name?: string };
    personalization?: { text?: string | null } | null;
    [key: string]: unknown;
  };
}

export interface Order {
  id: number;
  order_number: string;
  status: OrderStatus;
  status_label: string;
  currency: string;
  subtotal: number;
  delivery_fee: number;
  total: number;
  shipping: {
    recipient_name: string;
    phone: string;
    address_line1: string;
    address_line2: string | null;
    city: string;
    governorate: string;
    postal_code: string | null;
    country: string;
  };
  payment_method: PaymentMethod;
  payment_status: string;
  payment_summary: {
    deposit_amount: number;
    balance_amount: number;
    amount_paid: number;
    due_now: number;
  };
  ready_by: string;
  gift_options: { gift_wrap: boolean; gift_message: string | null };
  items: OrderItem[];
  created_at: string;
}

export interface OrderList {
  items: Order[];
  pagination: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface TimelineEvent {
  type: string;
  label: string;
  created_at: string;
}

export interface PaymentAttempt {
  redirect_url: string;
  amount: number;
  kind: "deposit" | "balance";
  method: PaymentMethod;
  status: string;
}

export const ordersApi = {
  list: (page = 1) => apiFetch<OrderList>("/orders", { auth: "required", query: { page } }),
  show: (id: string | number) => apiFetch<Order>(`/orders/${id}`, { auth: "required" }),
  timeline: (id: string | number) =>
    apiFetch<{ events: TimelineEvent[] }>(`/orders/${id}/timeline`, { auth: "required" }),
  pay: (id: number, kind: "deposit" | "balance", method: PaymentMethod) =>
    apiFetch<PaymentAttempt>(`/orders/${id}/payments`, {
      method: "POST",
      auth: "required",
      body: { kind, method },
      idempotencyKey: newIdempotencyKey(),
    }),
  cancel: (id: number) => apiFetch<unknown>(`/orders/${id}/cancel`, { method: "POST", auth: "required" }),
};
