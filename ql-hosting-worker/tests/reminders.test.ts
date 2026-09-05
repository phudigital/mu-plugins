import { describe, expect, it } from "vitest";
import { daysUntilVietnam } from "../src/services/reminders";

describe("daysUntilVietnam", () => {
  it("uses the Asia/Ho_Chi_Minh calendar day", () => {
    const now = new Date("2026-09-04T18:00:00.000Z");
    expect(daysUntilVietnam("2026-09-05", now)).toBe(0);
    expect(daysUntilVietnam("2026-09-06", now)).toBe(1);
  });
});
