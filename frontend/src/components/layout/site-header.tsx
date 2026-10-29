"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { Wordmark } from "@/components/brand/wordmark";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/features/auth/auth-provider";

export function SiteHeader() {
  const { status, user, logout } = useAuth();
  const router = useRouter();

  async function onLogout() {
    await logout();
    router.replace("/");
  }

  return (
    <header className="sticky top-0 z-40 border-b border-border/70 bg-background/85 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <Wordmark />

        <nav aria-label="Main" className="flex items-center gap-1 text-sm sm:gap-2">
          <Link href="/studio" className="rounded-md px-3 py-2 hover:bg-secondary">
            Studio
          </Link>
          {status === "authenticated" && (
            <Link href="/orders" className="rounded-md px-3 py-2 hover:bg-secondary">
              Orders
            </Link>
          )}

          {status === "loading" && <span aria-hidden className="h-9 w-20" />}

          {status === "guest" && (
            <Link href="/login" className="rounded-md px-3 py-2 hover:bg-secondary">
              Sign in
            </Link>
          )}

          {status === "authenticated" && user && (
            <>
              <span className="hidden max-w-[10rem] truncate px-2 text-muted-foreground sm:inline">{user.name}</span>
              <Button variant="ghost" size="sm" onClick={onLogout}>
                Sign out
              </Button>
            </>
          )}
        </nav>
      </div>
    </header>
  );
}
