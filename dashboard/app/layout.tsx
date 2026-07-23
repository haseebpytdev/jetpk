import type { Metadata } from "next";
import { DashboardShell } from "@/layouts/dashboard-shell";
import "./globals.css";

export const metadata: Metadata = {
  title: "JetPakistan Admin Preview",
  description: "Preview dashboard at /testdash — mock data only",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className="min-h-screen font-sans antialiased">
        <DashboardShell>{children}</DashboardShell>
      </body>
    </html>
  );
}
