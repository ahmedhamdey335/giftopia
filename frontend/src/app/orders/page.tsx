import type { Metadata } from "next";
import { RequireAuth } from "@/features/auth/require-auth";
import { OrderList } from "@/components/orders/order-list";

export const metadata: Metadata = { title: "My orders" };

export default function OrdersPage() {
  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-10 sm:px-6 sm:py-14">
      <div className="flex flex-col gap-2">
        <p className="text-xs uppercase tracking-[0.25em] text-bronze">Your account</p>
        <h1 className="text-4xl sm:text-5xl">My orders</h1>
        <p className="text-muted-foreground">Track each piece from deposit to delivery.</p>
      </div>
      <RequireAuth>
        <OrderList />
      </RequireAuth>
    </div>
  );
}
