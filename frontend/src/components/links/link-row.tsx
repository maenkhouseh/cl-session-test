"use client";

import { Archive, ArchiveRestore, BarChart3, CalendarClock, CopyPlus, FlaskConical, Folder as FolderIcon, Lock, MoreHorizontal, Pencil, QrCode, Share2, Trash2, UserPlus, MousePointerClick, DollarSign } from "lucide-react";
import Link from "next/link";
import { CopyButton } from "@/components/forms/copy-button";
import { Tooltip } from "@/components/ui/tooltip";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils/cn";
import { formatCompact, formatCurrency, timeAgo } from "@/lib/utils/format";
import { displayUrl, faviconUrl } from "@/lib/utils/url";
import type { Folder, Link as LinkModel } from "@/types/api";
import { TagBadge } from "./tag-badge";

export interface LinkRowActions {
  onEdit: (link: LinkModel) => void;
  onDuplicate: (link: LinkModel) => void;
  onQr: (link: LinkModel) => void;
  onShare: (link: LinkModel) => void;
  onArchive: (link: LinkModel) => void;
  onDelete: (link: LinkModel) => void;
}

interface Props extends LinkRowActions {
  link: LinkModel;
  workspace: string;
  folder?: Folder;
  selected: boolean;
  onSelect: (id: string, selected: boolean) => void;
}

export function LinkRow({ link, workspace, folder, selected, onSelect, ...actions }: Props) {
  const expired = !!link.expiresAt && new Date(link.expiresAt) < new Date();

  return (
    <li className={cn("group flex items-start gap-3 rounded-xl border border-border bg-card px-4 py-3 transition-colors hover:border-input", selected && "border-accent bg-accent/5", link.archived && "opacity-60")}>
      <input type="checkbox" aria-label={`Select ${link.shortLink}`} checked={selected} onChange={(e) => onSelect(link.id, e.target.checked)} className="mt-2.5 size-4 shrink-0 accent-[var(--accent)]" />
      <img src={faviconUrl(link.url)} alt="" width={36} height={36} className="mt-0.5 size-9 shrink-0 rounded-full border border-border bg-muted object-cover" loading="lazy" />

      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <Link href={`/app/${workspace}/analytics?linkId=${link.id}`} className="truncate font-medium hover:underline">
            {link.domain}/{link.key}
          </Link>
          <CopyButton value={link.shortLink} label={`Copy ${link.domain}/${link.key}`} message="Short link copied" />
          {link.tags.map((tag) => <TagBadge key={tag.id} tag={tag} />)}
        </div>
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
          <Tooltip content={link.url}><a href={link.url} target="_blank" rel="noopener noreferrer" className="max-w-[28rem] truncate hover:text-foreground hover:underline">↳ {link.title ? `${link.title} · ` : ""}{displayUrl(link.url)}</a></Tooltip>
          <span>{timeAgo(link.createdAt)}</span>
          {folder && <span className="inline-flex items-center gap-1"><FolderIcon className="size-3" />{folder.name}</span>}
          {link.hasPassword && <Tooltip content="Password protected"><Lock className="size-3" aria-label="Password protected" /></Tooltip>}
          {link.expiresAt && <Tooltip content={expired ? "Expired" : `Expires ${timeAgo(link.expiresAt)}`}><CalendarClock className={cn("size-3", expired && "text-destructive")} aria-label="Has expiration" /></Tooltip>}
          {link.testVariants && <Tooltip content="A/B test running"><FlaskConical className="size-3" aria-label="A/B test" /></Tooltip>}
          {link.archived && <span className="font-medium">Archived</span>}
        </div>
      </div>

      <dl className="hidden shrink-0 items-center gap-4 self-center text-sm tabular-nums sm:flex">
        <Metric icon={<MousePointerClick />} label="Clicks" value={formatCompact(link.clicks)} />
        {link.trackConversion && <Metric icon={<UserPlus />} label="Leads" value={formatCompact(link.leads)} />}
        {link.trackConversion && <Metric icon={<DollarSign />} label="Revenue" value={formatCurrency(link.saleAmount)} />}
      </dl>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="icon" className="self-center" aria-label={`Actions for ${link.domain}/${link.key}`}><MoreHorizontal /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onSelect={() => actions.onEdit(link)}><Pencil /> Edit</DropdownMenuItem>
          <DropdownMenuItem onSelect={() => actions.onDuplicate(link)}><CopyPlus /> Duplicate</DropdownMenuItem>
          <DropdownMenuItem onSelect={() => actions.onQr(link)}><QrCode /> QR code</DropdownMenuItem>
          <DropdownMenuItem asChild><Link href={`/app/${workspace}/analytics?linkId=${link.id}`}><BarChart3 /> Analytics</Link></DropdownMenuItem>
          <DropdownMenuItem onSelect={() => actions.onShare(link)}><Share2 /> Share analytics</DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem onSelect={() => actions.onArchive(link)}>{link.archived ? <><ArchiveRestore /> Unarchive</> : <><Archive /> Archive</>}</DropdownMenuItem>
          <DropdownMenuItem destructive onSelect={() => actions.onDelete(link)}><Trash2 /> Delete</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </li>
  );
}

function Metric({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return (
    <div className="flex items-center gap-1.5">
      <dt className="sr-only">{label}</dt>
      <span aria-hidden className="text-muted-foreground [&_svg]:size-3.5">{icon}</span>
      <dd className="font-medium">{value}</dd>
    </div>
  );
}
