import type { Metadata } from "next";
import { DashboardShell } from "@/layouts/dashboard-shell";
import { getDashboardSession } from "@/services/session-service";
import "./globals.css";

export const metadata: Metadata = {
  title: "JetPakistan Admin Preview",
  description: "Preview dashboard at /testdash — read-only integration",
};

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  let session = null;
  try {
    session = await getDashboardSession();
  } catch {
    session = null;
  }

  return (
    <html lang="en">
      <body className="min-h-screen font-sans antialiased">
        <DashboardShell session={session}>{children}</DashboardShell>
      </body>
    </html>
  );
}
