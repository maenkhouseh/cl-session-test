import type { Metadata } from "next";
import { LinksView } from "@/components/links/links-view";

export const metadata: Metadata = { title: "Links" };

export default function LinksPage() {
  return <LinksView />;
}
