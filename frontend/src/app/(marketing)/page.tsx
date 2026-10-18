import { BarChart3, Code2, Globe2, Link2, QrCode, ShieldCheck, Webhook, Zap } from "lucide-react";
import { CodeSample } from "@/components/marketing/code-sample";
import { CtaBanner } from "@/components/marketing/cta-banner";
import { DashboardPreview } from "@/components/marketing/dashboard-preview";
import { FeatureGrid } from "@/components/marketing/feature-grid";
import { Hero } from "@/components/marketing/hero";
import { Section } from "@/components/marketing/section";

const API_EXAMPLE = `curl https://api.shortie.example/api/v1/links \\
  -H "Authorization: Bearer shrt_••••••••" \\
  -H "Content-Type: application/json" \\
  -d '{ "url": "https://acme.com/launch", "key": "launch",
        "tagNames": ["campaign"], "trackConversion": true }'`;

export default function HomePage() {
  return (
    <>
      <Hero
        eyebrow="Link attribution for modern marketing teams"
        title={<>Turn clicks <span className="text-accent">into revenue</span></>}
        description="Shortie is the link attribution platform for short links, conversion tracking and real-time analytics — all in one place."
      >
        <DashboardPreview />
      </Hero>

      <Section eyebrow="Everything in one place" title="Marketing isn't just about clicks. It's about outcomes." description="Short links, conversion analytics and a developer-friendly API that scale with you.">
        <FeatureGrid
          features={[
            { icon: <Link2 />, title: "Branded short links", description: "Custom domains, tags, folders, UTM templates and bulk tools to keep campaigns organised." },
            { icon: <BarChart3 />, title: "Conversion analytics", description: "Follow every visitor from first click to lead to sale, filtered by country, device, referrer and more." },
            { icon: <Zap />, title: "Fast, reliable redirects", description: "Cache-first redirects with write-behind click tracking so analytics never slow your links down." },
            { icon: <QrCode />, title: "Customisable QR codes", description: "Generate branded QR codes for any link and tell scans apart from clicks in your analytics." },
            { icon: <Globe2 />, title: "Smart targeting", description: "Route visitors by country or device, deep-link into your app, and A/B test destinations." },
            { icon: <ShieldCheck />, title: "Control & privacy", description: "Password-protect or expire links, share read-only dashboards, and keep short links out of search results." },
          ]}
        />
      </Section>

      <Section eyebrow="For developers" title="Enterprise-grade link infrastructure" description="Create links, track conversions and react to events programmatically.">
        <div className="mx-auto grid max-w-5xl items-center gap-8 lg:grid-cols-2">
          <ul className="space-y-5">
            {[
              { icon: <Code2 />, t: "REST API", d: "Create, update, upsert and bulk-manage links, with analytics and event endpoints." },
              { icon: <Webhook />, t: "Signed webhooks", d: "Real-time link, lead and sale events with automatic retries and HMAC signatures." },
              { icon: <ShieldCheck />, t: "Scoped API keys", d: "Workspace-level keys with per-plan rate limits and one-time secret reveal." },
            ].map((x) => (
              <li key={x.t} className="flex gap-4">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent [&_svg]:size-5">{x.icon}</span>
                <div><h3 className="font-semibold">{x.t}</h3><p className="text-sm text-muted-foreground">{x.d}</p></div>
              </li>
            ))}
          </ul>
          <CodeSample title="POST /api/v1/links" code={API_EXAMPLE} />
        </div>
      </Section>

      <CtaBanner />
    </>
  );
}
