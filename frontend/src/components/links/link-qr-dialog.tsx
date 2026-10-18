"use client";

import { Download } from "lucide-react";
import * as React from "react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { qrUrl } from "@/lib/utils/url";
import type { Link } from "@/types/api";

export function LinkQrDialog({ link, open, onOpenChange }: { link: Link | null; open: boolean; onOpenChange: (open: boolean) => void }) {
  const [fg, setFg] = React.useState("#000000");
  const [bg, setBg] = React.useState("#ffffff");

  if (!link) return null;
  // `qr=1` lets analytics distinguish QR scans from plain link clicks.
  const target = `${link.shortLink}?qr=1`;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent title="QR code" description={link.shortLink.replace("https://", "")} className="max-w-sm">
        <div className="space-y-4">
          <img src={qrUrl(target, { size: 600, fg, bg })} alt={`QR code for ${link.shortLink}`} width={240} height={240} className="mx-auto rounded-lg border border-border" />
          <div className="grid grid-cols-2 gap-3">
            <ColorInput id="qr-fg" label="Foreground" value={fg} onChange={setFg} />
            <ColorInput id="qr-bg" label="Background" value={bg} onChange={setBg} />
          </div>
          <div className="flex gap-2">
            <Button asChild variant="secondary" className="flex-1"><a href={qrUrl(target, { size: 1000, fg, bg, format: "svg" })} download={`${link.key}-qr.svg`}><Download /> SVG</a></Button>
            <Button asChild variant="secondary" className="flex-1"><a href={qrUrl(target, { size: 1000, fg, bg, format: "png" })} download={`${link.key}-qr.png`}><Download /> PNG</a></Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function ColorInput({ id, label, value, onChange }: { id: string; label: string; value: string; onChange: (v: string) => void }) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <div className="flex items-center gap-2 rounded-lg border border-input bg-card px-2 py-1">
        <input id={id} type="color" value={value} onChange={(e) => onChange(e.target.value)} className="size-6 cursor-pointer rounded border-0 bg-transparent p-0" />
        <span className="font-mono text-xs uppercase text-muted-foreground">{value}</span>
      </div>
    </div>
  );
}
