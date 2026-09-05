import { describe, expect, it } from "vitest";
import { normalizeBrand, normalizeDateString, normalizeSettings } from "../src/services/normalize";

describe("normalizeDateString", () => {
  it("accepts display and storage date formats", () => {
    expect(normalizeDateString("05/09/2026")).toBe("2026-09-05");
    expect(normalizeDateString("2026-09-05")).toBe("2026-09-05");
  });

  it("rejects invalid dates", () => {
    expect(normalizeDateString("31/02/2026")).toBe("");
    expect(normalizeDateString("2026-13-01")).toBe("");
  });
});

describe("normalizeBrand", () => {
  it("keeps domains as a lower-case object", () => {
    const brand = normalizeBrand({
      domains: {
        " PDL.VN ": {
          expire: "05/09/2026",
          hosting_note: " VPS ",
          notify: { active: true, type: "warning", message: " Gia hạn " }
        }
      }
    });
    expect(brand.domains["pdl.vn"].expire).toBe("2026-09-05");
    expect(brand.domains["pdl.vn"].hosting_note).toBe("VPS");
    expect(brand.domains["pdl.vn"].notify.type).toBe("warning");
  });
});

describe("normalizeSettings", () => {
  it("preserves the encrypted token when the UI leaves token blank", () => {
    const settings = normalizeSettings({
      telegram: { enabled: true, chat_id: "123" },
      reminders: { days: [30, 7, 7, 1], repeat_after_days: 2 }
    }, {
      telegram: { enabled: false, chat_id: "", bot_token_encrypted: "v1:test" },
      reminders: { days: [30], notify_overdue: true, repeat_after_days: 1 },
      last_run: null
    });
    expect(settings.telegram.bot_token_encrypted).toBe("v1:test");
    expect(settings.reminders.days).toEqual([1, 7, 30]);
  });
});
