import type { Metadata } from "next";
import { WebhooksSettings } from "@/components/settings/webhooks-settings";

export const metadata: Metadata = { title: "Webhooks" };

export default function Page() {
  return <WebhooksSettings />;
}
