import Image from "next/image";
import Link from "next/link";
import { Eye, Flower2, MapPin } from "lucide-react";
import { Button } from "@/components/ui/button";

const highlights = [
  { icon: Flower2, label: "Real flowers & shells" },
  { icon: Eye, label: "Proof before pouring" },
  { icon: MapPin, label: "Delivered in Gharbia" },
];

export function Hero() {
  return (
    <section id="hero" className="surface-grain">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 pb-14 pt-8 sm:px-6 md:grid-cols-[1.1fr_0.9fr] md:items-center md:gap-12 md:py-20">
        <div className="flex flex-col items-start gap-6">
          <p className="text-xs uppercase tracking-[0.25em] text-bronze sm:text-sm">Handmade resin · Egypt</p>
          <h1 className="text-balance text-[2.75rem] leading-[1.02] sm:text-6xl lg:text-7xl">
            Poured by hand.
            <br />
            <span className="italic text-bronze">Designed by you.</span>
          </h1>
          <p className="max-w-md text-pretty text-base leading-relaxed text-muted-foreground sm:text-lg">
            Keepsakes and gifts made around a name, a letter or a memory. Choose the piece, the colours and the little
            details, and we show you a proof before anything is made.
          </p>

          <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
            <Button asChild size="lg" className="w-full sm:w-auto">
              <Link href="/studio">Start designing</Link>
            </Button>
            <Button asChild size="lg" variant="outline" className="w-full sm:w-auto">
              <Link href="#collections">See what we make</Link>
            </Button>
          </div>

          <ul className="flex flex-wrap gap-x-5 gap-y-2 pt-2 text-sm text-muted-foreground">
            {highlights.map(({ icon: Icon, label }) => (
              <li key={label} className="flex items-center gap-2">
                <Icon aria-hidden className="size-4 text-gold" />
                {label}
              </li>
            ))}
          </ul>
        </div>

        <div className="relative mx-auto w-full max-w-sm md:max-w-none">
          <div className="relative aspect-[4/5] overflow-hidden rounded-[2rem] border border-border bg-muted shadow-sm">
            <Image
              src="/images/hero-necklaces.png"
              alt="Handmade resin initial necklaces with real pressed flowers resting on a ceramic tray"
              fill
              priority
              sizes="(min-width: 768px) 45vw, 90vw"
              className="object-cover"
            />
          </div>
          <div className="absolute -bottom-5 start-4 flex items-center gap-3 rounded-2xl border border-border bg-card/95 px-4 py-3 shadow-sm backdrop-blur sm:start-6">
            <span className="font-display text-3xl leading-none text-bronze">7</span>
            <span className="text-xs leading-tight text-muted-foreground">
              days from deposit
              <br />
              to your door
            </span>
          </div>
        </div>
      </div>
      <div className="hairline-gold" />
    </section>
  );
}
