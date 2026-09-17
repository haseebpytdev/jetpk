import type { Metadata, Viewport } from "next";
import { IBM_Plex_Mono, Inter } from "next/font/google";
import { ThemeProvider } from "@/components/theme/ThemeProvider";
import { AppInteractionProviders } from "@/components/providers/AppInteractionProviders";
import { themeBootstrapScript } from "@/lib/theme/theme-bootstrap-script";
import { SkipLink } from "@/components/ui/SkipLink";
import { PublicConfigService } from "@/features/public-content/services/public-config-service";
import { resolveFaviconUrl } from "@/lib/branding/resolve-favicon";
import "./globals.css";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-body",
  display: "swap",
});

const ibmPlexMono = IBM_Plex_Mono({
  subsets: ["latin"],
  weight: ["400", "500", "600"],
  variable: "--font-mono",
  display: "swap",
});

export async function generateMetadata(): Promise<Metadata> {
  const config = await PublicConfigService.getConfig();
  const favicon = resolveFaviconUrl(config?.favicon_url);
  const brandName = config?.brand_name?.trim() || "JetPakistan";

  return {
    title: {
      default: brandName,
      template: `%s | ${brandName}`,
    },
    description: "Book flights, hotels, and travel services with JetPakistan.",
    icons: {
      icon: [{ url: favicon }],
      shortcut: [{ url: favicon }],
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
      <body className={`${inter.variable} ${ibmPlexMono.variable} font-sans antialiased`}>
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
