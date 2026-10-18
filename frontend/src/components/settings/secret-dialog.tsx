"use client";

import { TriangleAlert } from "lucide-react";
import { CopyButton } from "@/components/forms/copy-button";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent } from "@/components/ui/dialog";

/** One-time reveal of a secret (API key, webhook signing secret). It cannot be retrieved again. */
export function SecretDialog({ open, onOpenChange, title, secret, hint }: { open: boolean; onOpenChange: (o: boolean) => void; title: string; secret: string | null; hint: string }) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent title={title} className="max-w-md">
        <div className="space-y-4">
          <p className="flex gap-2 rounded-lg bg-warning/10 p-3 text-sm text-warning"><TriangleAlert className="mt-0.5 size-4 shrink-0" /> {hint}</p>
          <div className="flex items-center gap-1 rounded-lg border border-border bg-muted px-3 py-1.5">
            <code className="min-w-0 flex-1 break-all font-mono text-sm" data-testid="secret">{secret}</code>
            {secret && <CopyButton value={secret} label="Copy secret" message="Copied" />}
          </div>
          <div className="flex justify-end"><Button onClick={() => onOpenChange(false)}>Done</Button></div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
