import Link from "next/link";
import { Button } from "@/components/ui/button";

export default function HomePage() {
  return (
    <section className="surface-grain">
      <div className="mx-auto flex max-w-6xl flex-col items-start gap-8 px-4 py-20 sm:px-6 sm:py-28">
        <p className="text-sm uppercase tracking-[0.25em] text-bronze">Handmade resin · Egypt</p>
        <h1 className="max-w-3xl text-5xl leading-[1.05] sm:text-7xl">
          Poured by hand.
          <br />
          Designed by you.
        </h1>
        <p className="max-w-xl text-lg text-muted-foreground">
          Choose the piece, the mood, the pigments and the little details. We pour it, and show you a proof before
          anything is made.
        </p>
        <div className="flex flex-wrap items-center gap-4">
          <Button asChild size="lg">
            <Link href="/studio">Start designing</Link>
          </Button>
          <span className="text-sm text-muted-foreground">Ready about 7 days after your deposit</span>
        </div>
      </div>
      <div className="hairline-gold" />
    </section>
  );
}
