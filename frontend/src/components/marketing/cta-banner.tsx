import Link from "next/link";
import { Button } from "@/components/ui/button";

export function CtaBanner({ title = "Supercharge your link attribution", description = "Start tracking clicks and conversions in minutes. Free to start — no credit card required." }: { title?: string; description?: string }) {
  return (
    <section className="mx-auto max-w-6xl px-4 pb-20 sm:px-6">
      <div className="rounded-2xl bg-primary px-6 py-14 text-center text-primary-foreground sm:px-12">
        <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">{title}</h2>
        <p className="mx-auto mt-3 max-w-xl opacity-80">{description}</p>
        <div className="mt-8"><Button asChild size="lg" variant="secondary" className="text-foreground"><Link href="/register">Start for free</Link></Button></div>
      </div>
    </section>
  );
}
