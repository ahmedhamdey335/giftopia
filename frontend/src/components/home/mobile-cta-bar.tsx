"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

/** Thumb-reachable CTA on small screens, shown once the hero's own CTA has scrolled away. */
export function MobileCtaBar() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const hero = document.getElementById("hero");
    if (!hero) return;
    const observer = new IntersectionObserver(([entry]) => setVisible(!entry.isIntersecting), {
      rootMargin: "-40% 0px 0px 0px",
    });
    observer.observe(hero);
    return () => observer.disconnect();
  }, []);

  return (
    <div
      aria-hidden={!visible}
      className={cn(
        "fixed inset-x-0 bottom-0 z-30 border-t border-border bg-background/95 px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] pt-3 backdrop-blur transition-transform duration-300 md:hidden",
        visible ? "translate-y-0" : "pointer-events-none translate-y-full",
      )}
    >
      <div className="flex items-center gap-3">
        <p className="flex-1 text-xs leading-tight text-muted-foreground">
          Ready about 7 days
          <br />
          after your deposit
        </p>
        <Button asChild size="lg" className="flex-1">
          <Link href="/studio" tabIndex={visible ? undefined : -1}>
            Start designing
          </Link>
        </Button>
      </div>
    </div>
  );
}
