import type { TagColor, TagRef } from "@/types/api";
import { cn } from "@/lib/utils/cn";

const COLORS: Record<TagColor, string> = {
  red: "bg-red-500/15 text-red-700 dark:text-red-300",
  yellow: "bg-yellow-500/20 text-yellow-800 dark:text-yellow-300",
  green: "bg-green-500/15 text-green-700 dark:text-green-300",
  blue: "bg-blue-500/15 text-blue-700 dark:text-blue-300",
  purple: "bg-purple-500/15 text-purple-700 dark:text-purple-300",
  pink: "bg-pink-500/15 text-pink-700 dark:text-pink-300",
  brown: "bg-amber-800/15 text-amber-900 dark:text-amber-300",
};

export function TagBadge({ tag, className }: { tag: Pick<TagRef, "name" | "color">; className?: string }) {
  return <span className={cn("inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium", COLORS[tag.color] ?? COLORS.blue, className)}>{tag.name}</span>;
}

export const TAG_COLORS = Object.keys(COLORS) as TagColor[];
