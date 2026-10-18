import type { Metadata } from "next";
import { CtaBanner } from "@/components/marketing/cta-banner";
import { Faq } from "@/components/marketing/faq";
import { ComparisonTable, PricingCards } from "@/components/marketing/pricing-table";
import { Section } from "@/components/marketing/section";

export const metadata: Metadata = { title: "Pricing", description: "Flexible plans that grow with you." };

export default function PricingPage() {
  return (
    <>
      <Section title="Flexible plans that grow with you" description="Simple, transparent pricing. Every paid plan includes a 14-day trial." className="pb-8">
        <PricingCards />
      </Section>
      <Section title="Compare plans" className="pt-8"><ComparisonTable /></Section>
      <Section title="Frequently asked questions" className="pt-8"><Faq /></Section>
      <CtaBanner />
    </>
  );
}
