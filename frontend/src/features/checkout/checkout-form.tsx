"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { policyCopy } from "@/content/policies";
import { errorMessage, isApiError } from "@/lib/api/errors";
import { newIdempotencyKey } from "@/lib/api/client";
import { formatEgp } from "@/lib/money";
import { RequireAuth } from "@/features/auth/require-auth";
import { useAuth } from "@/features/auth/auth-provider";
import { useCatalogStore } from "@/features/studio/catalog/catalog-store";
import { useStudioStore } from "@/features/studio/store/studio-store";
import { checkoutApi, type CheckoutRequest } from "./checkout-api";

const checkoutSchema = z.object({
  shipping: z.object({
    recipient_name: z.string().trim().min(1, "Enter the recipient's name").max(255),
    phone: z.string().trim().min(7, "Enter a valid phone number").max(30),
    address_line1: z.string().trim().min(3, "Enter the street address").max(255),
    address_line2: z.string().trim().max(255),
    city: z.string().min(1, "Choose a city"),
    governorate: z.string().min(1, "Choose a governorate"),
    postal_code: z.string().trim().max(20),
  }),
  payment_method: z.enum(["card", "wallet"]),
  gift_options: z.object({
    gift_wrap: z.boolean(),
    gift_message: z.string().trim().max(500),
  }),
  acknowledgments: z.object({
    handmade_variation: z.boolean(),
    refund_policy: z.boolean(),
    proof_auto_approval: z.boolean(),
  }),
});

type CheckoutValues = z.infer<typeof checkoutSchema>;

const DEFAULT_VALUES: CheckoutValues = {
  shipping: {
    recipient_name: "",
    phone: "",
    address_line1: "",
    address_line2: "",
    city: "",
    governorate: "",
    postal_code: "",
  },
  payment_method: "card",
  gift_options: { gift_wrap: false, gift_message: "" },
  acknowledgments: { handmade_variation: false, refund_policy: false, proof_auto_approval: false },
};

function CheckoutContent() {
  const { user } = useAuth();
  const quote = useStudioStore((state) => state.quote);
  const selections = useStudioStore((state) => state.selections);
  const setQuote = useStudioStore((state) => state.setQuote);
  const catalog = useCatalogStore((state) => state.catalog);
  const catalogStatus = useCatalogStore((state) => state.status);
  const catalogError = useCatalogStore((state) => state.error);
  const loadCatalog = useCatalogStore((state) => state.load);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [idempotency, setIdempotency] = useState<{ payload: string; key: string } | null>(null);
  const [now, setNow] = useState(() => Date.now());
  const hasPersonalization = selections.text.trim().length > 0;
  const settings = catalog?.checkout;
  const quoteExpired = quote ? new Date(quote.expires_at).getTime() <= now : true;

  const schema = useMemo(
    () => checkoutSchema.refine((value) => !hasPersonalization || value.acknowledgments.proof_auto_approval, {
      path: ["acknowledgments", "proof_auto_approval"],
      message: "Acknowledge proof auto-approval to continue.",
    }),
    [hasPersonalization],
  );
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CheckoutValues>({ resolver: zodResolver(schema), defaultValues: DEFAULT_VALUES });

  useEffect(() => {
    if (!catalog) void loadCatalog();
  }, [catalog, loadCatalog]);
  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(timer);
  }, []);

  const onSubmit = async (values: CheckoutValues) => {
    setSubmitError(null);
    if (!quote || quoteExpired || !settings) {
      setSubmitError("Your quote has expired or is unavailable. Return to the studio and request a new quote.");
      return;
    }
    if (!values.acknowledgments.handmade_variation) {
      setError("acknowledgments.handmade_variation", { message: "Please acknowledge handmade variations." });
      return;
    }
    if (!values.acknowledgments.refund_policy) {
      setError("acknowledgments.refund_policy", { message: "Please acknowledge the refund policy." });
      return;
    }
    const request: CheckoutRequest = {
      quote_id: quote.quote_id,
      shipping: {
        ...values.shipping,
        address_line2: values.shipping.address_line2 || null,
        country: "Egypt",
      },
      payment_method: values.payment_method,
      gift_options: {
        gift_wrap: values.gift_options.gift_wrap,
        gift_message: values.gift_options.gift_message || null,
      },
      acknowledgments: {
        ...values.acknowledgments,
        proof_auto_approval: hasPersonalization && values.acknowledgments.proof_auto_approval,
      },
    };
    const payload = JSON.stringify(request);
    const requestIdempotency = idempotency?.payload === payload
      ? idempotency
      : { payload, key: newIdempotencyKey() };
    if (requestIdempotency !== idempotency) setIdempotency(requestIdempotency);
    try {
      const order = await checkoutApi.submit(request, requestIdempotency.key);
      setQuote(null);
      window.location.assign(order.payment.redirect_url);
    } catch (error) {
      if (isApiError(error) && error.status === 409) {
        setSubmitError("The catalog price changed. Return to the studio to review and request a fresh quote.");
      } else {
        setSubmitError(errorMessage(error, "We could not place your order. Please try again."));
      }
    }
  };

  if (!quote || quoteExpired) {
    return (
      <main className="mx-auto max-w-2xl space-y-4 px-4 py-12">
        <h1 className="text-3xl font-display">Checkout</h1>
        <p role="status">A current quote is required before checkout.</p>
        <Button asChild><Link href="/studio">Return to the studio</Link></Button>
      </main>
    );
  }

  if (!settings && catalogStatus !== "error") {
    return <main className="mx-auto max-w-2xl px-4 py-12" aria-live="polite">Loading checkout options…</main>;
  }

  if (!settings) {
    return (
      <main className="mx-auto max-w-2xl space-y-4 px-4 py-12">
        <h1 className="text-3xl font-display">Checkout</h1>
        <p role="alert" className="text-destructive">We could not load delivery options: {catalogError ?? "Catalog unavailable."}</p>
        <Button asChild variant="outline"><Link href="/studio">Return to the studio</Link></Button>
      </main>
    );
  }

  const deliveryFee = settings.delivery_fee;
  const checkoutTotal = quote.total + deliveryFee;
  const estimatedDeposit = Math.ceil((checkoutTotal * settings.deposit_percent) / 100);

  return (
    <main className="mx-auto grid max-w-6xl gap-8 px-4 py-10 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <section>
        <p className="text-sm uppercase tracking-[0.2em] text-bronze">Secure checkout</p>
        <h1 className="mt-1 text-4xl font-display">Delivery and payment</h1>
        <p className="mt-2 text-sm text-muted-foreground">Signed in as {user?.email}</p>
        <form onSubmit={handleSubmit(onSubmit)} noValidate className="mt-8 space-y-8">
          <fieldset className="space-y-4">
            <legend className="text-xl font-display">Delivery details</legend>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="recipient_name">Recipient name</Label>
                <Input id="recipient_name" autoComplete="name" aria-invalid={!!errors.shipping?.recipient_name} {...register("shipping.recipient_name")} />
                {errors.shipping?.recipient_name && <p className="text-sm text-destructive">{errors.shipping.recipient_name.message}</p>}
              </div>
              <div className="space-y-2">
                <Label htmlFor="phone">Phone</Label>
                <Input id="phone" type="tel" autoComplete="tel" aria-invalid={!!errors.shipping?.phone} {...register("shipping.phone")} />
                {errors.shipping?.phone && <p className="text-sm text-destructive">{errors.shipping.phone.message}</p>}
              </div>
              <div className="space-y-2 sm:col-span-2">
                <Label htmlFor="address_line1">Address</Label>
                <Input id="address_line1" autoComplete="address-line1" {...register("shipping.address_line1")} />
                {errors.shipping?.address_line1 && <p className="text-sm text-destructive">{errors.shipping.address_line1.message}</p>}
              </div>
              <div className="space-y-2 sm:col-span-2">
                <Label htmlFor="address_line2">Apartment, floor, or landmark (optional)</Label>
                <Input id="address_line2" autoComplete="address-line2" {...register("shipping.address_line2")} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="city">City</Label>
                <select id="city" className="h-11 w-full rounded-md border border-input bg-background px-3" {...register("shipping.city")}>
                  <option value="">Choose a city</option>
                  {settings.delivery_cities.map((city) => <option key={city} value={city}>{city}</option>)}
                </select>
                {errors.shipping?.city && <p className="text-sm text-destructive">{errors.shipping.city.message}</p>}
              </div>
              <div className="space-y-2">
                <Label htmlFor="governorate">Governorate</Label>
                <select id="governorate" className="h-11 w-full rounded-md border border-input bg-background px-3" {...register("shipping.governorate")}>
                  <option value="">Choose a governorate</option>
                  {settings.delivery_governorates.map((governorate) => <option key={governorate} value={governorate}>{governorate}</option>)}
                </select>
                {errors.shipping?.governorate && <p className="text-sm text-destructive">{errors.shipping.governorate.message}</p>}
              </div>
              <div className="space-y-2">
                <Label htmlFor="postal_code">Postal code (optional)</Label>
                <Input id="postal_code" autoComplete="postal-code" {...register("shipping.postal_code")} />
              </div>
            </div>
          </fieldset>

          <fieldset className="space-y-3">
            <legend className="text-xl font-display">Payment method</legend>
            {(["card", "wallet"] as const).map((method) => (
              <label key={method} className="flex cursor-pointer items-center gap-3 rounded-lg border border-border p-4">
                <input type="radio" value={method} {...register("payment_method")} />
                <span>{method === "card" ? "Card" : "Mobile wallet"}</span>
              </label>
            ))}
          </fieldset>

          <fieldset className="space-y-3">
            <legend className="text-xl font-display">Gift options</legend>
            <label className="flex items-center gap-3">
              <input type="checkbox" {...register("gift_options.gift_wrap")} />
              <span>Add gift wrap</span>
            </label>
            <div className="space-y-2">
              <Label htmlFor="gift_message">Gift message (optional)</Label>
              <textarea id="gift_message" maxLength={500} className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2" {...register("gift_options.gift_message")} />
            </div>
          </fieldset>

          <fieldset className="space-y-4">
            <legend className="text-xl font-display">Acknowledgments</legend>
            <label className="flex items-start gap-3">
              <input type="checkbox" className="mt-1" {...register("acknowledgments.handmade_variation")} />
              <span className="text-sm">{policyCopy.handmadeVariation}</span>
            </label>
            {errors.acknowledgments?.handmade_variation && <p className="text-sm text-destructive">{errors.acknowledgments.handmade_variation.message}</p>}
            <label className="flex items-start gap-3">
              <input type="checkbox" className="mt-1" {...register("acknowledgments.refund_policy")} />
              <span className="text-sm">{policyCopy.refundPolicy} Claim window: {settings.claim_window_days} days after delivery.</span>
            </label>
            {errors.acknowledgments?.refund_policy && <p className="text-sm text-destructive">{errors.acknowledgments.refund_policy.message}</p>}
            {hasPersonalization && (
              <>
                <label className="flex items-start gap-3">
                  <input type="checkbox" className="mt-1" {...register("acknowledgments.proof_auto_approval")} />
                  <span className="text-sm">{policyCopy.proofAutoApproval(settings.proof_auto_approve_hours)}</span>
                </label>
                {errors.acknowledgments?.proof_auto_approval && <p className="text-sm text-destructive">{errors.acknowledgments.proof_auto_approval.message}</p>}
              </>
            )}
          </fieldset>

          {submitError && <p role="alert" className="rounded-lg border border-destructive/40 p-3 text-sm text-destructive">{submitError}</p>}
          <Button type="submit" className="w-full" disabled={isSubmitting}>
            {isSubmitting ? "Placing order…" : `Pay deposit · ${formatEgp(estimatedDeposit)}`}
          </Button>
        </form>
      </section>

      <aside className="h-fit rounded-xl border border-border bg-card p-5 lg:sticky lg:top-24">
        <h2 className="text-xl font-display">Order summary</h2>
        <ul className="mt-4 divide-y divide-border">
          {quote.price_breakdown.items.map((item) => (
            <li key={`${item.key}-${item.id ?? "none"}`} className="flex justify-between gap-3 py-2 text-sm">
              <span>{item.name}</span><span className="shrink-0">{formatEgp(item.amount)}</span>
            </li>
          ))}
        </ul>
        <dl className="mt-3 space-y-2 border-t border-border pt-3 text-sm">
          <div className="flex justify-between gap-3"><dt>Delivery</dt><dd>{formatEgp(deliveryFee)}</dd></div>
          <div className="flex justify-between gap-3 font-semibold"><dt>Total</dt><dd>{formatEgp(checkoutTotal)}</dd></div>
          <div className="flex justify-between gap-3"><dt>Deposit due now ({settings.deposit_percent}%)</dt><dd>{formatEgp(estimatedDeposit)}</dd></div>
          <div className="flex justify-between gap-3"><dt>Balance later</dt><dd>{formatEgp(checkoutTotal - estimatedDeposit)}</dd></div>
        </dl>
        <p className="mt-4 text-xs text-muted-foreground">
          The server confirms final pricing and payment amounts when you place the order.
        </p>
      </aside>
    </main>
  );
}

export function CheckoutForm() {
  return (
    <RequireAuth fallback={<main className="mx-auto max-w-xl px-4 py-16" aria-live="polite">Checking your account…</main>}>
      <CheckoutContent />
    </RequireAuth>
  );
}
