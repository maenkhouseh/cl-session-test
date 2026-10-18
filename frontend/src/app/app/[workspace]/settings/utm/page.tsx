import type { Metadata } from "next";
import { UtmSettings } from "@/components/settings/utm-settings";

export const metadata: Metadata = { title: "UTM templates" };

export default function Page() {
  return <UtmSettings />;
}
