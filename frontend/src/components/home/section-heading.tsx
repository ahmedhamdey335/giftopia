import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

export function SectionHeading({
  id,
  eyebrow,
  title,
  description,
  action,
  className,
}: {
  id: string;
  eyebrow: string;
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between", className)}>
      <div className="flex max-w-xl flex-col gap-3">
        <p className="text-xs uppercase tracking-[0.25em] text-bronze">{eyebrow}</p>
        <h2 id={id} className="text-balance text-4xl leading-tight sm:text-5xl">
          {title}
        </h2>
        {description && <p className="text-pretty leading-relaxed text-muted-foreground">{description}</p>}
      </div>
      {action}
    </div>
  );
}
