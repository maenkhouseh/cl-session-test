"use client";

import { Check, Copy } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useCopy } from "@/lib/hooks/use-copy";

export function CopyButton({ value, label = "Copy", message }: { value: string; label?: string; message?: string }) {
  const { copied, copy } = useCopy();
  return (
    <Button type="button" variant="ghost" size="icon" aria-label={label} onClick={() => copy(value, message)}>
      {copied ? <Check className="text-success" /> : <Copy />}
    </Button>
  );
}
