import * as React from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export function SettingsCard({ title, description, actions, children }: { title: string; description?: string; actions?: React.ReactNode; children: React.ReactNode }) {
  return (
    <Card>
      <CardHeader className="py-4">
        <div>
          <CardTitle className="text-base">{title}</CardTitle>
          {description && <p className="mt-0.5 text-sm font-normal text-muted-foreground">{description}</p>}
        </div>
        {actions}
      </CardHeader>
      <CardContent>{children}</CardContent>
    </Card>
  );
}
