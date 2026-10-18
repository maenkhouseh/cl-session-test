"use client";

import { useFormContext } from "react-hook-form";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import type { LinkFormValues } from "@/lib/validation/link";

/** Expiration + password protection. */
export function AccessFields({ passwordSet }: { passwordSet: boolean }) {
  const { register } = useFormContext<LinkFormValues>();

  return (
    <div className="space-y-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Expires at" htmlFor="link-expires" hint="Local time">
          <Input id="link-expires" type="datetime-local" {...register("expiresAt")} />
        </Field>
        <Field label="Expired destination" htmlFor="link-expired-url" hint="Where visitors go after expiry">
          <Input id="link-expired-url" placeholder="https://acme.com/expired" {...register("expiredUrl")} />
        </Field>
      </div>
      <Field label="Password" htmlFor="link-password" hint={passwordSet ? "A password is set — type a new one to change it" : "Visitors must enter it before being redirected"}>
        <Input id="link-password" type="password" autoComplete="new-password" placeholder={passwordSet ? "••••••••" : ""} {...register("password")} />
      </Field>
    </div>
  );
}
