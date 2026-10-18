import * as React from "react";
import { cn } from "@/lib/utils/cn";

export function Section({ id, eyebrow, title, description, children, className }: { id?: string; eyebrow?: string; title: string; description?: string; children?: React.ReactNode; className?: string }) {
  return (
    <section id={id} className={cn("mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20", className)}>
      <div className="mx-auto max-w-2xl text-center">
        {eyebrow && <p className="text-sm font-semibold uppercase tracking-wide text-accent">{eyebrow}</p>}
        <h2 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{title}</h2>
        {description && <p className="mt-4 text-lg text-muted-foreground">{description}</p>}
      </div>
      {children && <div className="mt-12">{children}</div>}
    </section>
  );
}
