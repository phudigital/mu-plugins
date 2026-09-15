import { describe, expect, it, vi } from "vitest";
import { isVietnameseDomain, lookupDomainRegistrar, normalizeLookupDomain, RegistrarLookupError } from "../src/services/registrar";

describe("domain registrar lookup", () => {
  it("normalizes URLs and rejects unsafe or incomplete domain input", () => {
    expect(normalizeLookupDomain("https://www.Example.COM/path?q=1")).toBe("example.com");
    expect(normalizeLookupDomain("-bad.example")).toBe("");
    expect(normalizeLookupDomain("localhost")).toBe("");
    expect(isVietnameseDomain("pdl.vn")).toBe(true);
    expect(isVietnameseDomain("example.com")).toBe(false);
  });

  it("uses the IANA bootstrap and parses a registrar from RDAP", async () => {
    const fetcher = vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      if (url === "https://data.iana.org/rdap/dns.json") {
        return Response.json({ services: [[["com"], ["https://rdap.registry.test/"]]] });
      }
      expect(url).toBe("https://rdap.registry.test/domain/example.com");
      return Response.json({
        entities: [{ roles: ["registrar"], vcardArray: ["vcard", [["fn", {}, "text", "Example Registrar, Inc."]]] }],
        events: [
          { eventAction: "registration", eventDate: "2020-01-02T00:00:00Z" },
          { eventAction: "expiration", eventDate: "2027-01-02T00:00:00Z" }
        ],
        nameservers: [{ ldhName: "NS2.EXAMPLE.NET" }, { ldhName: "ns1.example.net" }],
        status: ["client transfer prohibited"]
      }, { headers: { "Content-Type": "application/rdap+json" } });
    });

    await expect(lookupDomainRegistrar("example.com", "", fetcher as typeof fetch)).resolves.toMatchObject({
      domain: "example.com",
      registrar: "Example Registrar, Inc.",
      source: "rdap",
      registered: true,
      created_at: "2020-01-02T00:00:00Z",
      expires_at: "2027-01-02T00:00:00Z",
      nameservers: ["ns1.example.net", "ns2.example.net"]
    });
    expect(fetcher).toHaveBeenCalledTimes(2);
  });

  it("uses BKNS for .vn without exposing the API key in the URL", async () => {
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = new URL(String(input));
      expect(url.origin).toBe("https://whois.bkns.vn");
      expect(url.searchParams.get("domain")).toBe("pdl.vn");
      expect(url.toString()).not.toContain("private-bkns-key");
      expect(new Headers(init?.headers).get("x-api-key")).toBe("private-bkns-key");
      return Response.json({
        status: "registered",
        data: {
          registrar: { name: "Công ty Cổ phần P.A Việt Nam" },
          dates: { created: "2010-02-01", expiry: "2028-02-01" },
          nameservers: ["ns1.pavietnam.vn"],
          domainStatus: ["clientTransferProhibited"]
        }
      });
    });

    await expect(lookupDomainRegistrar("pdl.vn", "private-bkns-key", fetcher as typeof fetch)).resolves.toMatchObject({
      domain: "pdl.vn",
      registrar: "Công ty Cổ phần P.A Việt Nam",
      source: "bkns",
      expires_at: "2028-02-01"
    });
    expect(fetcher).toHaveBeenCalledTimes(1);
  });

  it("requires a BKNS key for .vn and rejects private RDAP redirects", async () => {
    await expect(lookupDomainRegistrar("pdl.vn")).rejects.toMatchObject({ code: "bkns_key_required", status: 422 });

    const fetcher = vi.fn(async (input: RequestInfo | URL) => {
      if (String(input) === "https://data.iana.org/rdap/dns.json") {
        return Response.json({ services: [[["com"], ["https://rdap.registry.test/"]]] });
      }
      return new Response(null, { status: 302, headers: { Location: "https://127.0.0.1/private" } });
    });
    await expect(lookupDomainRegistrar("example.com", "", fetcher as typeof fetch)).rejects.toBeInstanceOf(RegistrarLookupError);
  });
});
