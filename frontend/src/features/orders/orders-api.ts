import { apiFetch, apiRequest, newIdempotencyKey } from "@/lib/api/client";
import type { QuoteSummary } from "../studio/types";

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

export interface ProofItem {
  item_id: number;
  version: number;
  text: string;
  font_name?: string | null;
  text_color_name?: string | null;
  note?: string | null;
  status: "pending" | "approved" | "changes_requested";
  requested_at: string;
  auto_approve_at: string;
  preview_url: string | null;
}

export interface RefundRequest {
  id: number;
  status: "requested" | "approved" | "rejected" | "refunded" | "failed";
  reason: string;
  requested_amount: number;
  approved_amount: number | null;
  decision_note: string | null;
  evidence: Array<{ id: string; url: string }>;
  created_at: string;
}

export interface ReorderSuccess {
  quote: QuoteSummary;
  draft: unknown;
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
  proofs: (id: string | number) =>
    apiFetch<{ items: ProofItem[] }>(`/orders/${id}/proof`, { auth: "required" }),
  approveProof: (id: string | number) =>
    apiFetch<{ approved_proof_ids: number[] }>(`/orders/${id}/proof/approve`, { method: "POST", auth: "required" }),
  requestProofChanges: (id: string | number, note: string) =>
    apiFetch<{ proof_ids: number[] }>(`/orders/${id}/proof/request-changes`, {
      method: "POST",
      auth: "required",
      body: { note },
    }),
  refundRequests: (id: string | number) =>
    apiFetch<{ items: RefundRequest[] }>(`/orders/${id}/refund-requests`, { auth: "required" }),
  uploadRefundEvidence: async (orderId: number, files: File[]): Promise<Array<{ id: string; url: string }>> => {
    const form = new FormData();
    form.append("order_id", String(orderId));
    files.forEach((file) => form.append("files[]", file));
    const { data } = await apiRequest<{ items: Array<{ id: string; url: string }> }>("/refund-evidence", {
      method: "POST",
      body: form,
      auth: "required",
      timeoutMs: 60_000,
    });
    return data.items;
  },
  createRefundRequest: (id: string | number, reason: string, evidenceAssetIds: string[] = []) =>
    apiFetch<RefundRequest>(`/orders/${id}/refund-requests`, {
      method: "POST",
      auth: "required",
      body: { reason, evidence_asset_ids: evidenceAssetIds },
    }),
  reorder: (id: string | number) =>
    apiFetch<ReorderSuccess>(`/orders/${id}/reorder`, { method: "POST", auth: "required" }),
};

