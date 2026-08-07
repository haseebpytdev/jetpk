import type { Metadata } from "next";
import { Inter, Space_Grotesk } from "next/font/google";
import { ThemeProvider } from "@/components/theme/ThemeProvider";
import { DashboardShell } from "@/layouts/dashboard-shell";
import { SessionProvider } from "@/lib/session-context";
import { themeBootstrapScript } from "@/lib/theme/theme-bootstrap-script";
import { getDashboardSession } from "@/services/session-service";
import "./globals.css";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-body",
  display: "swap",
});

const spaceGrotesk = Space_Grotesk({
  subsets: ["latin"],
  variable: "--font-display",
  display: "swap",
});

export const metadata: Metadata = {
  title: "JetPakistan Back Office",
  description: "JetPakistan admin and staff back-office dashboard",
  robots: { index: false, follow: false },
};

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  let session = null;
  try {
    session = await getDashboardSession();
  } catch {
    session = null;
  }

  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeBootstrapScript }} />
      </head>
      <body className={`${inter.variable} ${spaceGrotesk.variable} min-h-screen overflow-x-hidden font-sans antialiased`}>
        <ThemeProvider>
          <SessionProvider session={session}>
            <DashboardShell session={session}>{children}</DashboardShell>
          </SessionProvider>
        </ThemeProvider>
      </body>
    </html>
  );
}
