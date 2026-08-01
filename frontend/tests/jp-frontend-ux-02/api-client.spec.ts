import { test, expect } from "@playwright/test";
import { mapFieldErrors, mapStatusToErrorCode } from "../../lib/api/errors";

test.describe("JP-FRONTEND-UX-02 ajax helper", () => {
  test("maps HTTP statuses to error codes", () => {
    expect(mapStatusToErrorCode(401)).toBe("unauthorized");
    expect(mapStatusToErrorCode(403)).toBe("forbidden");
    expect(mapStatusToErrorCode(409)).toBe("conflict");
    expect(mapStatusToErrorCode(422)).toBe("validation");
    expect(mapStatusToErrorCode(429)).toBe("rate_limit");
    expect(mapStatusToErrorCode(500)).toBe("server");
  });

  test("maps field errors to first message", () => {
    expect(mapFieldErrors({ email: ["Invalid email", "Other"] })).toEqual({ email: "Invalid email" });
  });
});
