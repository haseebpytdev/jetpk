import { test, expect } from "@playwright/test";
import { laravelUserIdFromPublicId, publicUserIdFromLaravelId } from "@/lib/users/public-user-id";

test.describe("staff permissions id bridge", () => {
  test("maps JP-USR public ids to Laravel numeric ids", () => {
    expect(laravelUserIdFromPublicId("JP-USR-0007")).toBe("7");
    expect(laravelUserIdFromPublicId("JP-USR-42")).toBe("42");
    expect(laravelUserIdFromPublicId("15")).toBe("15");
    expect(laravelUserIdFromPublicId(15)).toBe("15");
  });

  test("maps Laravel numeric ids to padded public ids", () => {
    expect(publicUserIdFromLaravelId(7)).toBe("JP-USR-0007");
    expect(publicUserIdFromLaravelId("42")).toBe("JP-USR-0042");
    expect(publicUserIdFromLaravelId("JP-USR-0009")).toBe("JP-USR-0009");
  });
});
