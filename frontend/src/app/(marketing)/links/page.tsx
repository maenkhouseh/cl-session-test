import { Archive, CalendarClock, Eye, FlaskConical, FolderTree, Globe2, Lock, QrCode, Tag, Target } from "lucide-react";
import type { Metadata } from "next";
import { CtaBanner } from "@/components/marketing/cta-banner";
import { FeatureGrid } from "@/components/marketing/feature-grid";
import { Hero } from "@/components/marketing/hero";
import { Section } from "@/components/marketing/section";

export const metadata: Metadata = { title: "Short links", description: "Branded short links with custom domains, QR codes, targeting, A/B tests and more." };

export default function LinksPage() {
  return (
    <>
      <Hero title="Short links with superpowers" description="The modern link management platform for entrepreneurs, creators and growth teams." />
      <Section eyebrow="Branded short links" title="Links that stand out" description="Customise your short links, organise your campaigns and track what truly matters.">
        <FeatureGrid
          features={[
            { icon: <Globe2 />, title: "Custom domains", description: "Use go.yourbrand.com with DNS verification. A branded domain builds trust and lifts click-through." },
            { icon: <Tag />, title: "Tags & folders", description: "Organise thousands of links, filter lists and analytics, and control who can edit what." },
            { icon: <QrCode />, title: "QR codes", description: "Automatic, customisable QR codes with SVG and PNG export — scans are tracked separately." },
            { icon: <Target />, title: "Geo & device targeting", description: "Send visitors to the right place by country, or deep-link iOS and Android users into your app." },
            { icon: <FlaskConical />, title: "A/B tests", description: "Split traffic between up to four destinations and keep each visitor on the same variant." },
            { icon: <Eye />, title: "Custom link previews", description: "Control the title, description and image shown on social networks and in messaging apps." },
            { icon: <Lock />, title: "Password protection", description: "Gate a link behind a password, with secure unlock cookies that expire." },
            { icon: <CalendarClock />, title: "Expiration", description: "Expire links on a date and send late visitors to a fallback page." },
            { icon: <FolderTree />, title: "UTM builder & templates", description: "Create reusable UTM presets so every campaign link is tagged consistently." },
            { icon: <Archive />, title: "Bulk tools", description: "Create up to 100 links at once, then archive, move or delete in bulk." },
          ]}
        />
      </Section>
      <CtaBanner title="Short links with super powers" />
    </>
  );
}
