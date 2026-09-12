/**
 * Stops Laravel only when this Playwright run started it.
 */
export default async function globalTeardown() {
  if (process.env.PLAYWRIGHT_KEEP_LARAVEL === "1") {
    return;
  }

  if (process.env.PLAYWRIGHT_LARAVEL_STARTED !== "1") {
    return;
  }

  const pid = Number(process.env.PLAYWRIGHT_LARAVEL_PID ?? "0");
  if (!Number.isFinite(pid) || pid <= 0) {
    return;
  }

  try {
    process.kill(pid);
  } catch {
    /* process may already be gone */
  }
}
