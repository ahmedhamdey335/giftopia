import Link from "next/link";
import { Instagram } from "lucide-react";
import { Button } from "@/components/ui/button";
import { INSTAGRAM_HANDLE, INSTAGRAM_URL } from "@/content/home";

export function FinalCta() {
  return (
    <section aria-labelledby="cta-title" className="px-4 sm:px-6">
      <div className="mx-auto flex max-w-6xl flex-col items-start gap-6 rounded-[2rem] bg-primary px-6 py-12 text-primary-foreground sm:px-12 sm:py-16 md:flex-row md:items-center md:justify-between">
        <div className="flex max-w-lg flex-col gap-3">
          <h2 id="cta-title" className="text-balance text-4xl leading-tight sm:text-5xl">
            Gifts that make memories
          </h2>
          <p className="text-pretty leading-relaxed text-primary-foreground/80">
            Start with a name, a letter or a colour you love. You will see the price before you commit.
          </p>
        </div>
        <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
          <Button asChild size="lg" variant="secondary" className="w-full sm:w-auto">
            <Link href="/studio">Start designing</Link>
          </Button>
          <Button
            asChild
            size="lg"
            variant="ghost"
            className="w-full gap-2 text-primary-foreground hover:bg-primary-foreground/10 sm:w-auto"
          >
            <a href={INSTAGRAM_URL} target="_blank" rel="noopener noreferrer">
              <Instagram aria-hidden className="size-4" />
              {INSTAGRAM_HANDLE}
            </a>
          </Button>
        </div>
      </div>
    </section>
  );
}
