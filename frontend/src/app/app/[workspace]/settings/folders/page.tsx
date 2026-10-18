import type { Metadata } from "next";
import { FoldersSettings } from "@/components/settings/folders-settings";

export const metadata: Metadata = { title: "Folders" };

export default function Page() {
  return <FoldersSettings />;
}
