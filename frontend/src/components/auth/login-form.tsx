"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import * as React from "react";
import { useForm } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useLogin, useMe } from "@/lib/hooks/use-session";
import { loginSchema, type LoginValues } from "@/lib/validation/auth";
import { applyApiErrors, safeNext } from "@/lib/utils/forms";
import { AuthLayout } from "./auth-layout";

export function LoginForm() {
  const router = useRouter();
  const next = safeNext(useSearchParams().get("next"));
  const me = useMe();
  const [formError, setFormError] = React.useState<string | null>(null);
  const form = useForm<LoginValues>({ resolver: zodResolver(loginSchema), defaultValues: { email: "", password: "" } });
  const login = useLogin(() => router.replace(next));

  // Already signed in (e.g. returning to /login with a live session).
  React.useEffect(() => {
    if (me.isSuccess) router.replace(next);
  }, [me.isSuccess, next, router]);

  const onSubmit = form.handleSubmit((values) => {
    setFormError(null);
    login.mutate(values, { onError: (e) => setFormError(applyApiErrors(e, form.setError) ?? null) });
  });

  return (
    <AuthLayout
      title="Welcome back"
      description="Log in to your Shortie account"
      footer={<>Don&apos;t have an account? <Link href="/register" className="font-medium text-foreground underline-offset-4 hover:underline">Sign up</Link></>}
    >
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <Field label="Email" htmlFor="email" error={form.formState.errors.email?.message}>
          <Input id="email" type="email" autoComplete="email" aria-invalid={!!form.formState.errors.email} {...form.register("email")} />
        </Field>
        <Field label="Password" htmlFor="password" error={form.formState.errors.password?.message}>
          <Input id="password" type="password" autoComplete="current-password" aria-invalid={!!form.formState.errors.password} {...form.register("password")} />
        </Field>
        {formError && <p role="alert" className="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">{formError}</p>}
        <Button type="submit" className="w-full" loading={login.isPending}>Log in</Button>
      </form>
    </AuthLayout>
  );
}
