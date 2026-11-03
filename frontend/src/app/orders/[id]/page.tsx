import type { Metadata } from "next";
import { RequireAuth } from "@/features/auth/require-auth";
import { OrderDetail } from "@/components/orders/order-detail";

export const metadata: Metadata = { title: "Order details" };

export default async function OrderPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
      <RequireAuth>
        <OrderDetail id={id} />
      </RequireAuth>
    </div>
  );
}
