import Image from "next/image";
import { Eye, Flower2, Wallet } from "lucide-react";
import { promises } from "@/content/home";

const icons = [Flower2, Eye, Wallet];

export function Promises() {
  return (
    <section aria-labelledby="promises-title" className="py-16 sm:py-24">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 md:grid-cols-2 md:items-center md:gap-16">
        <div className="relative order-last aspect-[4/3] overflow-hidden rounded-[2rem] border border-border bg-muted md:order-first md:aspect-[4/5]">
          <Image
            src="/images/ocean-notebook.png"
            alt="Resin notebook cover with real seashells and gold calligraphy"
            fill
            sizes="(min-width: 768px) 45vw, 100vw"
            className="object-cover"
          />
        </div>

        <div className="flex flex-col gap-8">
          <div className="flex flex-col gap-3">
            <p className="text-xs uppercase tracking-[0.25em] text-bronze">Our promise</p>
            <h2 id="promises-title" className="text-balance text-4xl leading-tight sm:text-5xl">
              Made slowly, with real things inside
            </h2>
          </div>

          <ul className="flex flex-col gap-6">
            {promises.map((promise, index) => {
              const Icon = icons[index];
              return (
                <li key={promise.title} className="flex gap-4">
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent">
                    <Icon aria-hidden className="size-5 text-primary" />
                  </span>
                  <div className="flex flex-col gap-1">
                    <h3 className="text-xl">{promise.title}</h3>
                    <p className="text-sm leading-relaxed text-muted-foreground">{promise.description}</p>
                  </div>
                </li>
              );
            })}
          </ul>
        </div>
      </div>
    </section>
  );
}
