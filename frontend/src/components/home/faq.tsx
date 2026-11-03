import { ChevronDown } from "lucide-react";
import { faqs } from "@/content/home";
import { SectionHeading } from "./section-heading";

export function Faq() {
  return (
    <section id="faq" aria-labelledby="faq-title" className="scroll-mt-20 py-16 sm:py-24">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 md:grid-cols-[0.8fr_1.2fr] md:gap-16">
        <SectionHeading
          id="faq-title"
          eyebrow="Good to know"
          title="Questions, answered"
          description="Anything else? Send us a message on Instagram and we usually reply the same day."
        />

        <div className="divide-y divide-border border-y border-border">
          {faqs.map((faq) => (
            <details key={faq.question} className="group">
              <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-4 text-start font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring [&::-webkit-details-marker]:hidden">
                {faq.question}
                <ChevronDown
                  aria-hidden
                  className="size-5 shrink-0 text-bronze transition-transform group-open:rotate-180"
                />
              </summary>
              <p className="pb-5 pe-8 text-sm leading-relaxed text-muted-foreground">{faq.answer}</p>
            </details>
          ))}
        </div>
      </div>
    </section>
  );
}
