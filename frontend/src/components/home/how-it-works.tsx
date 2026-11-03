import { steps } from "@/content/home";
import { SectionHeading } from "./section-heading";

export function HowItWorks() {
  return (
    <section aria-labelledby="how-title" className="bg-secondary/60 py-16 sm:py-24">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <SectionHeading
          id="how-title"
          eyebrow="How it works"
          title="From idea to keepsake in four steps"
        />

        <ol className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {steps.map((step, index) => (
            <li key={step.title} className="flex gap-4 rounded-2xl border border-border bg-card p-5 lg:flex-col">
              <span
                aria-hidden
                className="flex size-10 shrink-0 items-center justify-center rounded-full border border-gold/60 font-display text-xl text-bronze"
              >
                {index + 1}
              </span>
              <div className="flex flex-col gap-1.5">
                <h3 className="text-xl">
                  <span className="sr-only">Step {index + 1}: </span>
                  {step.title}
                </h3>
                <p className="text-sm leading-relaxed text-muted-foreground">{step.description}</p>
              </div>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}
