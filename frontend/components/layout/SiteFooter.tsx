import { JetPakistanLogo } from "@/components/layout/JetPakistanLogo";
import { PageContainer } from "@/components/layout/PageContainer";
import { CurrencySelector } from "@/components/navigation/CurrencySelector";
import type { PublicConfig } from "@/features/public-content/services/public-config-service";
import { footerColumns, socialLinks } from "@/lib/navigation";
import { cn } from "@/lib/cn";

type SiteFooterProps = {
  className?: string;
  branding?: Pick<PublicConfig, "brand_name" | "logo_url" | "header_logo_height"> | null;
};

export function SiteFooter({ className, branding = null }: SiteFooterProps) {
  return (
    <footer className={cn("bg-jp-footer text-white", className)} role="contentinfo">
      <PageContainer className="py-7 sm:py-8">
        <div className="grid gap-7 lg:grid-cols-[1.25fr_repeat(4,minmax(0,1fr))] lg:gap-8">
          <div className="space-y-3">
            <JetPakistanLogo
              variant="inverse"
              logoUrl={branding?.logo_url}
              brandName={branding?.brand_name}
              logoHeight={branding?.header_logo_height}
            />
            <p className="max-w-sm text-jp-sm leading-relaxed text-white/80">
              Connecting you to the world with trusted fares, secure booking, and dedicated support for
              travelers across Pakistan and beyond.
            </p>
          </div>

          {footerColumns.map((column) => (
            <div key={column.title}>
              <h2 className="text-jp-xs font-semibold uppercase tracking-wide text-white">{column.title}</h2>
              <ul className="mt-3 space-y-1.5">
                {column.links.map((link) => (
                  <li key={link.href}>
                    <a
                      href={link.href}
                      className="text-jp-sm text-white/80 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    >
                      {link.label}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </PageContainer>

      <div className="border-t border-white/15">
        <PageContainer className="flex flex-col gap-3 py-3.5 text-jp-sm text-white/75 sm:flex-row sm:items-center sm:justify-between">
          <p>© {new Date().getFullYear()} JetPakistan. All rights reserved.</p>
          <div className="flex flex-wrap items-center gap-3 sm:justify-end">
            <CurrencySelector appearance="footer" />
            <div className="flex items-center gap-2">
              {socialLinks.map((link) => (
                <a
                  key={link.label}
                  href={link.href}
                  aria-label={link.label}
                  title={link.label}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/20 text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                >
                  <SocialIcon label={link.label} />
                </a>
              ))}
            </div>
          </div>
        </PageContainer>
      </div>
    </footer>
  );
}

function SocialIcon({ label }: { label: string }) {
  const key = label.toLowerCase();
  if (key.includes("facebook") || key.includes("meta")) {
    return (
      <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true" fill="currentColor">
        <path d="M14 8.2h2.1V5.1c-.3 0-1.5-.1-2.8-.1-2.8 0-4.7 1.7-4.7 4.8v2.1H6.3v3.4h2.3V22h3.5v-6.7h2.5l.4-3.4h-2.9V10c0-1 .3-1.8 1.9-1.8Z" />
      </svg>
    );
  }
  if (key.includes("instagram")) {
    return (
      <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true" fill="currentColor">
        <path d="M12 7.2A4.8 4.8 0 1 0 12 16.8 4.8 4.8 0 0 0 12 7.2Zm0 7.9a3.1 3.1 0 1 1 0-6.2 3.1 3.1 0 0 1 0 6.2Zm5.1-8.2a1.1 1.1 0 1 1-2.2 0 1.1 1.1 0 0 1 2.2 0ZM12 3.5c-2.3 0-2.6 0-3.5.1-2.3.1-3.5 1.3-3.6 3.6-.1.9-.1 1.2-.1 3.5s0 2.6.1 3.5c.1 2.3 1.3 3.5 3.6 3.6.9.1 1.2.1 3.5.1s2.6 0 3.5-.1c2.3-.1 3.5-1.3 3.6-3.6.1-.9.1-1.2.1-3.5s0-2.6-.1-3.5c-.1-2.3-1.3-3.5-3.6-3.6-.9-.1-1.2-.1-3.5-.1Zm0 1.6c2.3 0 2.5 0 3.4.1 1.7.1 2.5.9 2.6 2.6.1.9.1 1.1.1 3.4s0 2.5-.1 3.4c-.1 1.7-.9 2.5-2.6 2.6-.9.1-1.1.1-3.4.1s-2.5 0-3.4-.1c-1.7-.1-2.5-.9-2.6-2.6-.1-.9-.1-1.1-.1-3.4s0-2.5.1-3.4c.1-1.7.9-2.5 2.6-2.6.9-.1 1.1-.1 3.4-.1Z" />
      </svg>
    );
  }
  if (key.includes("linkedin")) {
    return (
      <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true" fill="currentColor">
        <path d="M6.3 9.3H3.6V20h2.7V9.3ZM4.9 4a1.6 1.6 0 1 0 0 3.2A1.6 1.6 0 0 0 4.9 4ZM20.4 20h-2.7v-5.2c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V20h-2.7V9.3h2.6v1.5h.1c.4-.7 1.3-1.5 2.7-1.5 2.9 0 3.4 1.9 3.4 4.4V20Z" />
      </svg>
    );
  }
  return <span className="text-jp-xs font-semibold">{label.slice(0, 2)}</span>;
}
