import type { FieldValues, Path, UseFormSetError } from "react-hook-form";
import { ApiError } from "@/lib/api/client";

/**
 * Copies Laravel validation errors onto react-hook-form fields.
 * Returns the message to show at form level when nothing could be mapped to a field.
 */
export function applyApiErrors<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>, fieldMap: Record<string, Path<T>> = {}): string | null {
  if (!(error instanceof ApiError)) return "Something went wrong. Please try again.";

  let mapped = false;
  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    const target = (fieldMap[field] ?? field) as Path<T>;
    setError(target, { type: "server", message: messages[0] });
    mapped = true;
  }

  return mapped ? null : error.message;
}

/** Same-origin, in-app redirects only — never follow an arbitrary `?next=` URL. */
export function safeNext(next: string | null | undefined, fallback = "/app"): string {
  return next && next.startsWith("/") && !next.startsWith("//") ? next : fallback;
}
