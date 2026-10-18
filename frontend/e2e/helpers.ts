import { expect, type Page } from "@playwright/test";

/** Short keys are unique per domain across ALL workspaces, so every run needs its own. */
export const uniqueKey = (prefix: string) => `${prefix}-${Date.now().toString(36)}${Math.floor(Math.random() * 1296).toString(36)}`;

export const uniqueEmail = () => `e2e+${Date.now()}${Math.floor(Math.random() * 1000)}@example.com`;

export async function register(page: Page, { name = "E2E User", workspace = "E2E Co" } = {}) {
  const email = uniqueEmail();
  await page.goto("/register");
  await page.getByLabel("Full name").fill(name);
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Password", { exact: true }).fill("password123");
  await page.getByLabel("Confirm password").fill("password123");
  await page.getByLabel("Workspace name").fill(workspace);
  await page.getByRole("button", { name: "Create account" }).click();
  await page.waitForURL(/\/app\/[^/]+\/links/);
  await expect(page.getByRole("heading", { name: "Links", exact: true })).toBeVisible();
  return { email, slug: page.url().split("/app/")[1].split("/")[0] };
}

import { execSync } from "node:child_process";
import path from "node:path";

const backendDir = () => process.env.BACKEND_DIR ?? path.resolve(__dirname, "../../backend");
const artisan = (command: string) => {
  try {
    return execSync(`php artisan ${command}`, { cwd: backendDir(), stdio: "pipe" });
  } catch (error) {
    const e = error as { stdout?: Buffer; stderr?: Buffer };
    throw new Error(`artisan ${command} failed:\n${e.stderr?.toString() ?? ""}${e.stdout?.toString() ?? ""}`);
  }
};

/** Drain the click buffer immediately instead of waiting for the 10-second scheduler tick. */
export function flushClicks() {
  artisan("clicks:flush");
}

/** Switch a workspace plan (there is no billing integration; operators set it). */
export function setPlan(slug: string, plan: "free" | "business" | "advanced" | "enterprise") {
  artisan(`workspace:set-plan ${slug} ${plan}`);
}
