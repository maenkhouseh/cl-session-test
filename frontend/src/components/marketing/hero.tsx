import Link from "next/link";
import * as React from "react";
import { Button } from "@/components/ui/button";

export function Hero({ eyebrow, title, description, children }: { eyebrow?: string; title: React.ReactNode; description: string; children?: React.ReactNode }) {
  return (
    <section className="relative overflow-hidden">
      <div aria-hidden className="absolute inset-x-0 top-0 -z-10 h-[32rem] bg-[radial-gradient(60%_60%_at_50%_0%,color-mix(in_srgb,var(--accent)_18%,transparent),transparent)]" />
      <div className="mx-auto max-w-4xl px-4 pb-10 pt-20 text-center sm:px-6 sm:pt-28">
        {eyebrow && <p className="mx-auto mb-5 w-fit rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">{eyebrow}</p>}
        <h1 className="text-balance text-4xl font-semibold tracking-tight sm:text-6xl">{title}</h1>
        <p className="mx-auto mt-6 max-w-2xl text-balance text-lg text-muted-foreground">{description}</p>
        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <Button asChild size="lg"><Link href="/register">Start for free</Link></Button>
          <Button asChild size="lg" variant="secondary"><Link href="/pricing">See pricing</Link></Button>
        </div>
      </div>
      {children}
    </section>
  );
}
