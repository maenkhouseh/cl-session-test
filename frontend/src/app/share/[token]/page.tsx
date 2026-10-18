import type { Metadata } from "next";
import { PublicDashboard } from "@/components/share/public-dashboard";

// Shared dashboards are private by default; owners opt in to indexing server-side via the API.
export const metadata: Metadata = { title: "Analytics", robots: { index: false, follow: false } };

export default async function SharePage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  return <PublicDashboard token={token} />;
}
