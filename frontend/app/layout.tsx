import type { Metadata, Viewport } from "next";
import { IBM_Plex_Mono, Plus_Jakarta_Sans } from "next/font/google";
import { ThemeProvider } from "@/components/theme/ThemeProvider";
import { AppInteractionProviders } from "@/components/providers/AppInteractionProviders";
import { PublicConfigService } from "@/features/public-content/services/public-config-service";
import { themeBootstrapScript } from "@/lib/theme/theme-bootstrap-script";
import { SkipLink } from "@/components/ui/SkipLink";
import "./globals.css";

const plusJakartaSans = Plus_Jakarta_Sans({
  subsets: ["latin"],
  weight: ["400", "600"],
  variable: "--font-body",
  display: "swap",
  preload: false,
});

const ibmPlexMono = IBM_Plex_Mono({
  subsets: ["latin"],
  weight: ["400", "500"],
  variable: "--font-mono",
  display: "swap",
  preload: false,
});

export async function generateMetadata(): Promise<Metadata> {
  const config = await PublicConfigService.getConfig();
  const faviconUrl = config?.favicon_url?.trim();

  return {
    title: {
      default: config?.default_seo?.title?.trim() || "JetPakistan",
      template: "%s | JetPakistan",
    },
    description:
      config?.default_seo?.description?.trim() ||
      "Book flights, hotels, and travel services with JetPakistan.",
    icons: faviconUrl
      ? {
          icon: [{ url: faviconUrl }],
          shortcut: [{ url: faviconUrl }],
          apple: [{ url: faviconUrl }],
        }
      : {
          icon: [{ url: "/favicon.ico" }],
          shortcut: [{ url: "/favicon.ico" }],
        },
  };
}

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#edf3f7" },
    { media: "(prefers-color-scheme: dark)", color: "#0d1520" },
  ],
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" data-theme="light" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeBootstrapScript }} />
      </head>
      <body className={`${plusJakartaSans.variable} ${ibmPlexMono.variable} font-sans antialiased`}>
        <ThemeProvider>
          <AppInteractionProviders>
            <SkipLink />
            {children}
          </AppInteractionProviders>
        </ThemeProvider>
      </body>
    </html>
  );
}
