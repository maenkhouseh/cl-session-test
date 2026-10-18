import { WorkspaceGate } from "@/components/layout/workspace-gate";

export default async function WorkspaceLayout({ children, params }: { children: React.ReactNode; params: Promise<{ workspace: string }> }) {
  const { workspace } = await params;
  return <WorkspaceGate slug={workspace}>{children}</WorkspaceGate>;
}
