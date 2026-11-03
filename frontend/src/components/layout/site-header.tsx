"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Menu, X } from "lucide-react";
import { Wordmark } from "@/components/brand/wordmark";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/features/auth/auth-provider";
import { cn } from "@/lib/utils";

interface NavItem {
  href: string;
  label: string;
}

function isActive(pathname: string, href: string) {
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function SiteHeader() {
  const { status, user, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [menuOpen, setMenuOpen] = useState(false);
  const [menuPath, setMenuPath] = useState(pathname);

  // Close the mobile menu whenever the route changes.
  if (menuPath !== pathname) {
    setMenuPath(pathname);
    setMenuOpen(false);
  }

  useEffect(() => {
    if (!menuOpen) return;
    const onKey = (event: KeyboardEvent) => event.key === "Escape" && setMenuOpen(false);
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [menuOpen]);

  const navItems: NavItem[] = [
    { href: "/studio", label: "Studio" },
    ...(status === "authenticated" ? [{ href: "/orders", label: "My orders" }] : []),
    { href: "/#faq", label: "FAQ" },
  ];

  async function onLogout() {
    setMenuOpen(false);
    await logout();
    router.replace("/");
  }

  return (
    <header className="sticky top-0 z-40 border-b border-border/70 bg-background/90 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <Wordmark />

        <nav aria-label="Main" className="hidden items-center gap-1 text-sm md:flex">
          {navItems.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              aria-current={isActive(pathname, item.href) ? "page" : undefined}
              className={cn(
                "rounded-md px-3 py-2 transition-colors hover:bg-secondary",
                isActive(pathname, item.href) && "bg-secondary font-medium",
              )}
            >
              {item.label}
            </Link>
          ))}

          <span aria-hidden className="mx-2 h-5 w-px bg-border" />

          {status === "loading" && <span aria-hidden className="h-9 w-20" />}

          {status === "guest" && (
            <Link href="/login" className="rounded-md px-3 py-2 hover:bg-secondary">
              Sign in
            </Link>
          )}

          {status === "authenticated" && user && (
            <>
              <span className="max-w-[10rem] truncate px-2 text-muted-foreground">{user.name}</span>
              <Button variant="ghost" size="sm" onClick={onLogout}>
                Sign out
              </Button>
            </>
          )}

          {!isActive(pathname, "/studio") && (
            <Button asChild size="sm" className="ms-2">
              <Link href="/studio">Start designing</Link>
            </Button>
          )}
        </nav>

        <Button
          variant="ghost"
          size="icon"
          className="md:hidden"
          aria-expanded={menuOpen}
          aria-controls="mobile-menu"
          aria-label={menuOpen ? "Close menu" : "Open menu"}
          onClick={() => setMenuOpen((open) => !open)}
        >
          {menuOpen ? <X aria-hidden className="size-5" /> : <Menu aria-hidden className="size-5" />}
        </Button>
      </div>

      <div
        id="mobile-menu"
        hidden={!menuOpen}
        className="border-t border-border/70 bg-background px-4 pb-6 pt-2 md:hidden"
      >
        <nav aria-label="Mobile" className="flex flex-col">
          {navItems.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              onClick={() => setMenuOpen(false)}
              aria-current={isActive(pathname, item.href) ? "page" : undefined}
              className={cn(
                "flex min-h-12 items-center border-b border-border/60 font-display text-2xl",
                isActive(pathname, item.href) && "text-bronze",
              )}
            >
              {item.label}
            </Link>
          ))}
          {status === "guest" && (
            <Link
              href="/login"
              onClick={() => setMenuOpen(false)}
              className="flex min-h-12 items-center border-b border-border/60 font-display text-2xl"
            >
              Sign in
            </Link>
          )}
        </nav>

        <div className="mt-6 flex flex-col gap-3">
          <Button asChild size="lg">
            <Link href="/studio" onClick={() => setMenuOpen(false)}>
              Start designing
            </Link>
          </Button>
          {status === "authenticated" && user && (
            <div className="flex items-center justify-between gap-3 pt-2 text-sm">
              <span className="truncate text-muted-foreground">Signed in as {user.name}</span>
              <Button variant="outline" size="sm" onClick={onLogout}>
                Sign out
              </Button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
