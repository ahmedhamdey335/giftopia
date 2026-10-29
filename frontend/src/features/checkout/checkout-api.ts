import { apiFetch } from "@/lib/api/client";

export interface CheckoutRequest {
  quote_id: string;
  shipping: {
    recipient_name: string;
    phone: string;
    address_line1: string;
    address_line2: string | null;
    city: string;
    governorate: string;
    postal_code: string;
    country: "Egypt";
  };
  payment_method: "card" | "wallet";
  gift_options: {
    gift_wrap: boolean;
    gift_message: string | null;
  };
  acknowledgments: {
    handmade_variation: boolean;
    refund_policy: boolean;
    proof_auto_approval: boolean;
  };
}

export interface CheckoutResponse {
  id: number;
  order_number: string;
  status: string;
  total: number;
  deposit_amount: number;
  balance_amount: number;
  payment: {
    redirect_url: string;
    amount: number;
    expires_at: string;
  };
}

export const checkoutApi = {
  submit: (request: CheckoutRequest, idempotencyKey: string) =>
    apiFetch<CheckoutResponse>("/checkout", {
      method: "POST",
      body: request,
      auth: "required",
      idempotencyKey,
    }),
};
