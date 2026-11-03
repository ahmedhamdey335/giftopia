import Image from "next/image";
import Link from "next/link";
import { ArrowRight } from "lucide-react";
import { categories } from "@/content/home";
import { SectionHeading } from "./section-heading";

export function Collections() {
  return (
    <section id="collections" aria-labelledby="collections-title" className="scroll-mt-20 py-16 sm:py-24">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <SectionHeading
          id="collections-title"
          eyebrow="What we make"
          title="Little pieces, big meaning"
          description="Every piece starts from one of our bases. You choose everything else in the Studio."
          action={
            <Link
              href="/studio"
              className="hidden items-center gap-2 text-sm font-medium text-bronze underline-offset-4 hover:underline sm:inline-flex"
            >
              Design your own
              <ArrowRight aria-hidden className="size-4" />
            </Link>
          }
        />
      </div>

      <ul
        className="mt-10 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 [scrollbar-width:none] sm:px-6 md:mx-auto md:grid md:max-w-6xl md:grid-cols-3 md:overflow-visible md:pb-0"
        aria-label="Product types"
      >
        {categories.map((item) => (
          <li key={item.title} className="w-[72%] shrink-0 snap-start sm:w-[45%] md:w-auto">
            <Link
              href="/studio"
              className="group flex h-full flex-col overflow-hidden rounded-2xl border border-border bg-card transition-shadow hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <div className="relative aspect-square overflow-hidden bg-muted">
                <Image
                  src={item.image}
                  alt={item.alt}
                  fill
                  sizes="(min-width: 768px) 30vw, 72vw"
                  className="object-cover transition-transform duration-500 group-hover:scale-105"
                />
              </div>
              <div className="flex flex-1 flex-col gap-1 p-4">
                <h3 className="text-2xl">{item.title}</h3>
                <p className="text-sm leading-relaxed text-muted-foreground">{item.description}</p>
              </div>
            </Link>
          </li>
        ))}
      </ul>

      <p className="mt-2 px-4 text-xs text-muted-foreground md:hidden" aria-hidden>
        Swipe to see more
      </p>
    </section>
  );
}
