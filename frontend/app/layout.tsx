import type { Metadata, Viewport } from "next";
import { IBM_Plex_Mono, Inter, Space_Grotesk } from "next/font/google";
import { ThemeProvider } from "@/components/theme/ThemeProvider";
import { AppInteractionProviders } from "@/components/providers/AppInteractionProviders";
import { themeBootstrapScript } from "@/lib/theme/theme-bootstrap-script";
import { SkipLink } from "@/components/ui/SkipLink";
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

const ibmPlexMono = IBM_Plex_Mono({
  subsets: ["latin"],
  weight: ["400", "500", "600"],
  variable: "--font-mono",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "JetPakistan",
    template: "%s | JetPakistan",
  },
  description: "Book flights, hotels, and travel services with JetPakistan.",
};

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
      <body className={`${inter.variable} ${spaceGrotesk.variable} ${ibmPlexMono.variable} font-sans antialiased`}>
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
