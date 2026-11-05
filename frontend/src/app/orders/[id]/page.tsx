import type { Metadata } from "next";
import { Suspense } from "react";
import { RequireAuth } from "@/features/auth/require-auth";
import { OrderDetail } from "@/components/orders/order-detail";

export const metadata: Metadata = { title: "Order details" };

async function OrderPageContent({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <OrderDetail id={id} />;
}

export default function OrderPage({ params }: { params: Promise<{ id: string }> }) {
  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
      <RequireAuth>
        <Suspense fallback={<div className="h-48 animate-pulse rounded-2xl bg-muted" />}>
          <OrderPageContent params={params} />
        </Suspense>
      </RequireAuth>
    </div>
  );
}
