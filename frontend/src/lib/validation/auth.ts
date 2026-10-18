import { z } from "zod";

export const loginSchema = z.object({
  email: z.string().min(1, "Enter your email").email("Enter a valid email"),
  password: z.string().min(1, "Enter your password"),
});

export const registerSchema = z
  .object({
    name: z.string().trim().min(1, "Enter your name").max(100),
    email: z.string().min(1, "Enter your email").email("Enter a valid email"),
    password: z.string().min(8, "Use at least 8 characters"),
    password_confirmation: z.string(),
    workspace_name: z.string().trim().max(100).optional(),
  })
  .refine((v) => v.password === v.password_confirmation, { path: ["password_confirmation"], message: "Passwords don't match" });

export const workspaceSchema = z.object({
  name: z.string().trim().min(1, "Give your workspace a name").max(100),
  slug: z
    .string()
    .trim()
    .max(48)
    .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, "Lowercase letters, numbers and dashes only")
    .optional()
    .or(z.literal("")),
});

export type LoginValues = z.infer<typeof loginSchema>;
export type RegisterValues = z.infer<typeof registerSchema>;
export type WorkspaceValues = z.infer<typeof workspaceSchema>;
