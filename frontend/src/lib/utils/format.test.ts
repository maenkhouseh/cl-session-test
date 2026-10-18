import { describe, expect, it } from "vitest";
import { continentName, countryFlag, countryName, formatCompact, formatCurrency, formatPercent, timeAgo } from "./format";
import { displayUrl, hostOf, qrUrl, toQuery } from "./url";

describe("format", () => {
  it("compacts numbers", () => {
    expect(formatCompact(999)).toBe("999");
    expect(formatCompact(1234)).toBe("1.2K");
    expect(formatCompact(2_500_000)).toBe("2.5M");
  });

  it("formats cents as currency, dropping empty cents", () => {
    expect(formatCurrency(4900)).toBe("$49");
    expect(formatCurrency(4950)).toBe("$49.50");
    expect(formatCurrency(123456, "eur")).toBe("€1,234.56");
  });

  it("computes percentages safely", () => {
    expect(formatPercent(1, 3)).toBe("33.3%");
    expect(formatPercent(5, 0)).toBe("0%");
  });

  it("describes relative times", () => {
    const now = new Date("2026-06-15T12:00:00Z");
    expect(timeAgo("2026-06-15T11:57:00Z", now)).toBe("3 minutes ago");
    expect(timeAgo("2026-06-13T12:00:00Z", now)).toBe("2 days ago");
    expect(timeAgo("2026-06-15T12:00:20Z", now)).toBe("just now");
  });

  it("renders country info", () => {
    expect(countryFlag("DE")).toBe("🇩🇪");
    expect(countryFlag(null)).toBe("🌐");
    expect(countryName("DE")).toBe("Germany");
    expect(continentName("EU")).toBe("Europe");
    expect(continentName(undefined)).toBe("Unknown");
  });
});

describe("url helpers", () => {
  it("shortens destinations for display", () => {
    expect(displayUrl("https://www.example.com/")).toBe("example.com");
    expect(displayUrl("https://example.com/a/very/long/path/that/keeps/going/and/going/forever/and/ever", 20)).toHaveLength(20);
    expect(displayUrl("not a url")).toBe("not a url");
    expect(hostOf("https://www.example.com/x")).toBe("example.com");
  });

  it("builds a QR url", () => {
    expect(qrUrl("https://shortie.link/abc", { size: 300, fg: "#ff0000", format: "png" })).toBe("/api/v1/qr?url=https%3A%2F%2Fshortie.link%2Fabc&size=300&fgColor=ff0000&format=png");
  });

  it("drops empty values from query strings and joins arrays", () => {
    expect(toQuery({ a: 1, b: "", c: undefined, d: null, e: ["x", "y"], f: [], g: false })).toBe("?a=1&e=x%2Cy&g=false");
    expect(toQuery({})).toBe("");
  });
});
