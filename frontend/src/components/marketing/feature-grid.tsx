import * as React from "react";

export interface Feature {
  icon: React.ReactNode;
  title: string;
  description: string;
}

export function FeatureGrid({ features }: { features: Feature[] }) {
  return (
    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {features.map((f) => (
        <li key={f.title} className="rounded-xl border border-border bg-card p-5">
          <span className="flex size-10 items-center justify-center rounded-lg bg-accent/10 text-accent [&_svg]:size-5">{f.icon}</span>
          <h3 className="mt-4 font-semibold">{f.title}</h3>
          <p className="mt-1.5 text-sm text-muted-foreground">{f.description}</p>
        </li>
      ))}
    </ul>
  );
}
