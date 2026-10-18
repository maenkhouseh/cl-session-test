import { SettingsNav } from "@/components/settings/settings-nav";
import { PageHeader } from "@/components/layout/page-header";

export default async function SettingsLayout({ children, params }: { children: React.ReactNode; params: Promise<{ workspace: string }> }) {
  const { workspace } = await params;
  return (
    <div className="space-y-6">
      <PageHeader title="Settings" description="Manage your workspace, team and integrations." />
      <div className="flex flex-col gap-6 lg:flex-row">
        <SettingsNav slug={workspace} />
        <div className="min-w-0 flex-1 space-y-6">{children}</div>
      </div>
    </div>
  );
}
