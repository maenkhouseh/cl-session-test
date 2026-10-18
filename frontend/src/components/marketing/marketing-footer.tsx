import Link from "next/link";
import { Logo } from "@/components/layout/logo";

const COLUMNS = [
  { title: "Product", links: [{ href: "/links", label: "Short links" }, { href: "/analytics", label: "Analytics" }, { href: "/pricing", label: "Pricing" }] },
  { title: "Account", links: [{ href: "/login", label: "Log in" }, { href: "/register", label: "Sign up" }] },
];

export function MarketingFooter() {
  return (
    <footer className="border-t border-border">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[2fr_1fr_1fr]">
        <div className="space-y-3">
          <Logo />
          <p className="max-w-xs text-sm text-muted-foreground">The modern link attribution platform for short links, conversion tracking and real-time analytics.</p>
        </div>
        {COLUMNS.map((col) => (
          <div key={col.title}>
            <h2 className="mb-3 text-sm font-semibold">{col.title}</h2>
            <ul className="space-y-2 text-sm text-muted-foreground">
              {col.links.map((l) => <li key={l.href}><Link href={l.href} className="hover:text-foreground">{l.label}</Link></li>)}
            </ul>
          </div>
        ))}
      </div>
      <p className="border-t border-border py-5 text-center text-xs text-muted-foreground">© {new Date().getFullYear()} Shortie. All rights reserved.</p>
    </footer>
  );
}
