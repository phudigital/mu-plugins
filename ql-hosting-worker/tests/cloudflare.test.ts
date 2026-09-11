import { describe, expect, it, vi } from "vitest";
import { CloudflareError, listCloudflareZones } from "../src/services/cloudflare";

describe("listCloudflareZones", () => {
  it("reads every page, normalizes names, and never sends the token in the URL", async () => {
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = new URL(String(input));
      expect(url.origin).toBe("https://api.cloudflare.com");
      expect(url.searchParams.has("api_token")).toBe(false);
      expect(new Headers(init?.headers).get("authorization")).toBe("Bearer read-only-token");
      const page = Number(url.searchParams.get("page"));
      return Response.json({
        success: true,
        result_info: { total_pages: 2 },
        result: page === 1
          ? [{ name: " PDL.VN ", status: "active", paused: false, type: "full", account: { name: "PDL" } }]
          : [
              { name: "pdl.vn", status: "active", paused: false, type: "full", account: { name: "PDL" } },
              { name: "360vr.com.vn", status: "pending", paused: false, type: "full", account: { name: "PDL" } }
            ]
      });
    });

    await expect(listCloudflareZones("read-only-token", fetcher as typeof fetch)).resolves.toEqual([
      { name: "360vr.com.vn", status: "pending", paused: false, type: "full", account_name: "PDL" },
      { name: "pdl.vn", status: "active", paused: false, type: "full", account_name: "PDL" }
    ]);
    expect(fetcher).toHaveBeenCalledTimes(2);
  });

  it("reports rejected tokens without including the token value", async () => {
    const fetcher = vi.fn(async () => Response.json({ success: false, errors: [{ message: "Forbidden" }] }, { status: 403 }));
    let caught: unknown;
    try {
      await listCloudflareZones("never-leak-this", fetcher as typeof fetch);
    } catch (error) {
      caught = error;
    }
    expect(caught).toBeInstanceOf(CloudflareError);
    expect((caught as Error).message).not.toContain("never-leak-this");
    expect((caught as CloudflareError).status).toBe(422);
  });
});
