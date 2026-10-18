import { expect, test } from "@playwright/test";
import { register } from "./helpers";

test.describe("marketing site", () => {
  test("home, links, analytics and pricing render with working navigation", async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("heading", { level: 1 })).toContainText("Turn clicks");
    await page.getByRole("link", { name: "Pricing" }).first().click();
    await expect(page.getByRole("heading", { name: /Flexible plans/ })).toBeVisible();
    await expect(page.getByRole("table", { name: "Plan comparison" })).toBeVisible();
    await page.goto("/links");
    await expect(page.getByRole("heading", { level: 1 })).toContainText("superpowers");
    await page.goto("/analytics");
    await expect(page.getByRole("heading", { level: 1 })).toContainText("link attribution");
  });

  test("dashboard routes redirect anonymous visitors to login", async ({ page }) => {
    await page.goto("/app/anything/links");
    await expect(page).toHaveURL(/\/login\?next=/);
  });
});

test.describe("authentication", () => {
  test("shows validation and API errors inline", async ({ page }) => {
    await page.goto("/login");
    await page.getByRole("button", { name: "Log in" }).click();
    await expect(page.getByText("Enter your email")).toBeVisible();

    await page.getByLabel("Email").fill("nobody@example.com");
    await page.getByLabel("Password").fill("wrong-password");
    await page.getByRole("button", { name: "Log in" }).click();
    await expect(page.locator("form [role=alert]")).toContainText("Invalid email or password");
  });

  test("register → dashboard → logout → login", async ({ page }) => {
    const { email } = await register(page);
    await page.getByRole("button", { name: /E2E User/ }).click();
    await page.getByRole("menuitem", { name: "Log out" }).click();
    await page.waitForURL("**/login");

    await page.getByLabel("Email").fill(email);
    await page.getByLabel("Password").fill("password123");
    await page.getByRole("button", { name: "Log in" }).click();
    await page.waitForURL(/\/app\/.+\/links/);
  });
});
