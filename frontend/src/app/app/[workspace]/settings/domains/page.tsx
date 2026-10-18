import type { Metadata } from "next";
import { DomainsSettings } from "@/components/settings/domains-settings";

export const metadata: Metadata = { title: "Domains" };

export default function Page() {
  return <DomainsSettings />;
}
