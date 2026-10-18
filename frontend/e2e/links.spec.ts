import { expect, test } from "@playwright/test";
import { flushClicks, register, setPlan, uniqueKey } from "./helpers";

const BACKEND = process.env.BACKEND_URL ?? "http://127.0.0.1:8000";

test.describe("links", () => {
  test("create → redirect → analytics → edit → archive → delete", async ({ page, request }) => {
    const { slug } = await register(page);
    const key = uniqueKey("e2e");
    await expect(page.getByText("No links yet")).toBeVisible();

    // Create (keyboard shortcut "C" opens the dialog).
    await page.keyboard.press("c");
    const dialog = page.getByRole("dialog", { name: "Create a new link" });
    await dialog.getByLabel("Destination URL").fill("example.com/landing");
    await dialog.getByLabel("Short link").fill(key);
    await dialog.getByRole("button", { name: "Create link" }).click();
    await expect(page.getByRole("link", { name: `shortie.link/${key}` })).toBeVisible();

    // The short link really redirects (real Laravel redirect engine, Host-based routing).
    const redirect = await request.get(`${BACKEND}/${key}`, { headers: { Host: "shortie.link", "User-Agent": "Mozilla/5.0 (Macintosh) Chrome/124.0 Safari/537.36", "CF-IPCountry": "DE" }, maxRedirects: 0 });
    expect(redirect.status()).toBe(302);
    expect(redirect.headers()["location"]).toBe("https://example.com/landing");

    // …and the click appears in analytics once the buffer is flushed.
    flushClicks();
    await page.goto(`/app/${slug}/analytics`);
    await expect(page.getByRole("tab", { name: /Clicks/ })).toContainText("1");
    await page.getByRole("button", { name: /Germany/ }).click(); // filter by country via the Locations list
    await expect(page).toHaveURL(/country=DE/);
    await expect(page.getByLabel("Active filters")).toContainText("Country is Germany");

    // Edit.
    await page.goto(`/app/${slug}/links`);
    await page.getByRole("button", { name: `Actions for shortie.link/${key}` }).click();
    await page.getByRole("menuitem", { name: "Edit" }).click();
    const edit = page.getByRole("dialog", { name: "Edit link" });
    await edit.getByLabel("Comments").fill("Launch campaign");
    await edit.getByRole("button", { name: "Save changes" }).click();
    await expect(page.getByText("Link updated")).toBeVisible();

    // Archive hides it; the Archived switch reveals it.
    await page.getByRole("button", { name: `Actions for shortie.link/${key}` }).click();
    await page.getByRole("menuitem", { name: "Archive" }).click();
    await expect(page.getByText("No links yet")).toBeVisible();
    await page.getByRole("switch", { name: "Show archived" }).click();
    await expect(page.getByText("Archived", { exact: true }).first()).toBeVisible();

    // Delete.
    await page.getByRole("button", { name: `Actions for shortie.link/${key}` }).click();
    await page.getByRole("menuitem", { name: "Delete" }).click();
    await page.getByRole("dialog").getByRole("button", { name: "Delete" }).click();
    await expect(page.getByText("Link deleted")).toBeVisible();
    const gone = await request.get(`${BACKEND}/${key}`, { headers: { Host: "shortie.link" }, maxRedirects: 0 });
    expect(gone.status()).toBe(404);
  });

  test("duplicate keys show an inline error and the dialog stays open", async ({ page }) => {
    await register(page);
    const key = uniqueKey("dup");
    for (const expectSuccess of [true, false]) {
      await page.getByRole("button", { name: /Create link/ }).first().click();
      const dialog = page.getByRole("dialog", { name: "Create a new link" });
      await dialog.getByLabel("Destination URL").fill("https://example.com");
      await dialog.getByLabel("Short link").fill(key);
      await dialog.getByRole("button", { name: "Create link" }).click();
      if (expectSuccess) await expect(page.getByRole("link", { name: `shortie.link/${key}` })).toBeVisible();
      else await expect(dialog.getByRole("alert")).toContainText("already exists");
    }
  });

  test("paid options are locked on the free plan and unlock on upgrade", async ({ page }) => {
    const { slug } = await register(page);
    await page.getByRole("button", { name: /Create link/ }).first().click();
    const dialog = page.getByRole("dialog", { name: "Create a new link" });
    await expect(dialog.getByText("Upgrade").first()).toBeVisible();
    await dialog.getByRole("button", { name: /Expiration & password/ }).click();
    await expect(dialog.getByText("isn't included in your current plan")).toBeVisible();
    await page.keyboard.press("Escape");

    setPlan(slug, "business");
    await page.reload();
    await page.getByRole("button", { name: /Create link/ }).first().click();
    const unlocked = page.getByRole("dialog", { name: "Create a new link" });
    await unlocked.getByRole("button", { name: /Expiration & password/ }).click();
    await expect(unlocked.getByLabel("Password")).toBeVisible();
  });

  test("QR code dialog, public share dashboard and a bulk delete", async ({ page, request }) => {
    const { slug } = await register(page);
    setPlan(slug, "business");
    await page.reload();

    const [one, two] = [uniqueKey("qr"), uniqueKey("qr")];
    for (const key of [one, two]) {
      await page.getByRole("button", { name: /Create link/ }).first().click();
      const dialog = page.getByRole("dialog", { name: "Create a new link" });
      await dialog.getByLabel("Destination URL").fill("https://example.com/qr");
      await dialog.getByLabel("Short link").fill(key);
      await dialog.getByRole("button", { name: "Create link" }).click();
      await expect(page.getByRole("link", { name: `shortie.link/${key}` })).toBeVisible();
    }

    await page.getByRole("button", { name: `Actions for shortie.link/${one}` }).click();
    await page.getByRole("menuitem", { name: "QR code" }).click();
    await expect(page.getByRole("img", { name: /QR code for/ })).toBeVisible();
    const svg = await request.get(await page.getByRole("img", { name: /QR code for/ }).getAttribute("src").then((s) => `http://localhost:3000${s}`));
    expect(svg.headers()["content-type"]).toContain("image/svg+xml");
    await page.keyboard.press("Escape");

    await page.getByRole("button", { name: `Actions for shortie.link/${one}` }).click();
    await page.getByRole("menuitem", { name: "Share analytics" }).click();
    await page.getByRole("switch", { name: "Enable public sharing" }).click();
    const url = await page.getByLabel("Public link", { exact: true }).inputValue();
    expect(url).toMatch(/\/share\/dash_/);
    await page.keyboard.press("Escape");

    const anon = await page.context().browser()!.newContext();
    const shared = await anon.newPage();
    await shared.goto(url);
    await expect(shared.getByRole("heading", { level: 1 })).toContainText(`shortie.link/${one}`);
    await expect(shared.getByRole("tab", { name: /Clicks/ })).toBeVisible();
    await anon.close();

    await page.getByLabel("Select all on this page").check();
    await expect(page.getByRole("toolbar", { name: "Bulk actions" })).toContainText("2 selected");
    await page.getByRole("toolbar").getByRole("button", { name: "Delete" }).click();
    await page.getByRole("dialog").getByRole("button", { name: "Delete" }).click();
    await expect(page.getByText("No links yet")).toBeVisible();
  });
});
