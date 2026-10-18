"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";
import { useForm } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useRegister } from "@/lib/hooks/use-session";
import { applyApiErrors } from "@/lib/utils/forms";
import { registerSchema, type RegisterValues } from "@/lib/validation/auth";
import { AuthLayout } from "./auth-layout";

export function RegisterForm() {
  const router = useRouter();
  const [formError, setFormError] = React.useState<string | null>(null);
  const form = useForm<RegisterValues>({ resolver: zodResolver(registerSchema), defaultValues: { name: "", email: "", password: "", password_confirmation: "", workspace_name: "" } });
  const registerUser = useRegister(() => router.replace("/app"));
  const { errors } = form.formState;

  const onSubmit = form.handleSubmit((values) => {
    setFormError(null);
    registerUser.mutate({ ...values, workspace_name: values.workspace_name || undefined }, { onError: (e) => setFormError(applyApiErrors(e, form.setError)) });
  });

  return (
    <AuthLayout
      title="Create your account"
      description="Start tracking links in minutes — free to start"
      footer={<>Already have an account? <Link href="/login" className="font-medium text-foreground underline-offset-4 hover:underline">Log in</Link></>}
    >
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <Field label="Full name" htmlFor="name" error={errors.name?.message}>
          <Input id="name" autoComplete="name" aria-invalid={!!errors.name} {...form.register("name")} />
        </Field>
        <Field label="Email" htmlFor="email" error={errors.email?.message}>
          <Input id="email" type="email" autoComplete="email" aria-invalid={!!errors.email} {...form.register("email")} />
        </Field>
        <Field label="Password" htmlFor="password" error={errors.password?.message} hint="At least 8 characters">
          <Input id="password" type="password" autoComplete="new-password" aria-invalid={!!errors.password} {...form.register("password")} />
        </Field>
        <Field label="Confirm password" htmlFor="password_confirmation" error={errors.password_confirmation?.message}>
          <Input id="password_confirmation" type="password" autoComplete="new-password" aria-invalid={!!errors.password_confirmation} {...form.register("password_confirmation")} />
        </Field>
        <Field label="Workspace name" htmlFor="workspace_name" error={errors.workspace_name?.message} hint="Optional — you can change it later">
          <Input id="workspace_name" placeholder="Acme Inc." {...form.register("workspace_name")} />
        </Field>
        {formError && <p role="alert" className="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">{formError}</p>}
        <Button type="submit" className="w-full" loading={registerUser.isPending}>Create account</Button>
      </form>
    </AuthLayout>
  );
}
