import { Activity, BarChart3, Download, Filter, Share2, Users, Webhook } from "lucide-react";
import type { Metadata } from "next";
import { CtaBanner } from "@/components/marketing/cta-banner";
import { DashboardPreview } from "@/components/marketing/dashboard-preview";
import { FeatureGrid } from "@/components/marketing/feature-grid";
import { Hero } from "@/components/marketing/hero";
import { Section } from "@/components/marketing/section";

export const metadata: Metadata = { title: "Analytics", description: "Real-time click, lead and sale analytics with full attribution." };

export default function AnalyticsPage() {
  return (
    <>
      <Hero eyebrow="Real-time analytics" title="Supercharge your link attribution" description="From first click to conversion, understand exactly how your marketing drives revenue.">
        <DashboardPreview />
      </Hero>
      <Section eyebrow="Success at a glance" title="Measure what matters" description="Powerful, fast analytics with the breakdowns your team actually uses.">
        <FeatureGrid
          features={[
            { icon: <BarChart3 />, title: "Clicks, leads & sales", description: "Track the whole funnel in one dashboard with unique-visitor counts and revenue." },
            { icon: <Filter />, title: "Filter by anything", description: "Country, city, device, browser, referrer, UTM, tag, folder or QR scans — combine filters and exclude values." },
            { icon: <Activity />, title: "Real-time events stream", description: "Inspect every click, lead and sale as it happens, with keyset pagination for deep history." },
            { icon: <Users />, title: "Customer insights", description: "See each customer's journey, first-touch link and lifetime value." },
            { icon: <Share2 />, title: "Shareable dashboards", description: "Give partners or investors a read-only, optionally password-protected view of any link." },
            { icon: <Download />, title: "CSV export", description: "Download events for any filter set — safe against spreadsheet formula injection." },
            { icon: <Webhook />, title: "Webhooks & API", description: "Pipe events into your own stack with signed webhooks and a complete REST API." },
          ]}
        />
      </Section>
      <CtaBanner />
    </>
  );
}
