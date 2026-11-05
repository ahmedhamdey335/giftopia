"use client";

import { usePathname, useRouter } from "next/navigation";
import { Suspense, useEffect, type ReactNode } from "react";
import { useAuth } from "./auth-provider";

function RequireAuthContent({ children, fallback = null }: { children: ReactNode; fallback?: ReactNode }) {
  const { status } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (status === "guest") {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [status, router, pathname]);

  if (status !== "authenticated") return <>{fallback}</>;
  return <>{children}</>;
}

/** Wrap pages that need a signed-in customer (checkout, orders). Redirects guests to /login?next=. */
export function RequireAuth({ children, fallback = null }: { children: ReactNode; fallback?: ReactNode }) {
  return (
    <Suspense fallback={fallback}>
      <RequireAuthContent fallback={fallback}>{children}</RequireAuthContent>
    </Suspense>
  );
}
