const JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

async function ensureCsrfToken(): Promise<string | null> {
  const existing = readCookie("XSRF-TOKEN");
  if (existing) {
    return existing;
  }

  try {
    const response = await fetch("/api/public/content/csrf-token", {
      credentials: "include",
      headers: JSON_HEADERS,
      cache: "no-store",
    });
    if (!response.ok) {
      return null;
    }
    return readCookie("XSRF-TOKEN");
  } catch {
    return null;
  }
}

type LogoutResult =
  | { ok: true; redirect: string }
  | { ok: false; message: string };

export async function postLaravelLogout(): Promise<LogoutResult> {
  const csrf = await ensureCsrfToken();
  const headers: Record<string, string> = { ...JSON_HEADERS };
  if (csrf) {
    headers["X-XSRF-TOKEN"] = csrf;
  }

  try {
    const response = await fetch("/logout", {
      method: "POST",
      credentials: "include",
      headers,
      body: new FormData(),
    });

    const payload = (await response.json().catch(() => null)) as
      | { ok?: boolean; redirect?: string; message?: string }
      | null;

    if (!response.ok) {
      return {
        ok: false,
        message: payload?.message ?? "Unable to sign out. Try again.",
      };
    }

    return {
      ok: true,
      redirect: payload?.redirect ?? "/login",
    };
  } catch {
    return { ok: false, message: "Unable to sign out. Try again." };
  }
}
