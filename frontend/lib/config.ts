export const appConfig = {
  appUrl: process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000",
  laravelUrl: process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://127.0.0.1:8000",
  defaultCurrency: process.env.NEXT_PUBLIC_DEFAULT_CURRENCY ?? "PKR",
  sessionPreview: process.env.NEXT_PUBLIC_SESSION_PREVIEW ?? "logged-out",
} as const;

export type AppConfig = typeof appConfig;
