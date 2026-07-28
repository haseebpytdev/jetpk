"use client";

type AuthStatusBannerProps = {
  tone?: "info" | "success" | "error";
  message: string;
  live?: boolean;
};

const toneClasses = {
  info: "border-jp-border bg-jp-primary-soft text-jp-text",
  success: "border-jp-success/30 bg-jp-success/10 text-jp-text",
  error: "border-jp-danger/30 bg-jp-danger/10 text-jp-text",
} as const;

export function AuthStatusBanner({ tone = "info", message, live = false }: AuthStatusBannerProps) {
  return (
    <div
      role={tone === "error" ? "alert" : "status"}
      aria-live={live ? "polite" : undefined}
      className={`mb-4 rounded-jp-md border px-4 py-3 text-jp-sm ${toneClasses[tone]}`}
    >
      {message}
    </div>
  );
}
