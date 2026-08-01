import { JetPakistanLogo } from "@/components/layout/JetPakistanLogo";
import { PageContainer } from "@/components/layout/PageContainer";
import { footerColumns, socialLinks } from "@/lib/navigation";
import { cn } from "@/lib/cn";

type SiteFooterProps = {
  className?: string;
};

export function SiteFooter({ className }: SiteFooterProps) {
  return (
    <footer className={cn("bg-jp-footer text-white", className)} role="contentinfo">
      <PageContainer className="py-3 lg:py-4">
        <div className="grid gap-3 lg:grid-cols-[1.2fr_repeat(4,minmax(0,1fr))] lg:gap-5">
          <div className="space-y-2">
            <JetPakistanLogo variant="inverse" />
            <p className="max-w-xs text-jp-xs leading-relaxed text-white/80">
              Connecting you to the world with trusted fares, secure booking, and dedicated support for
              travelers across Pakistan and beyond.
            </p>
            <div className="flex flex-wrap gap-2">
              {socialLinks.map((link) => (
                <a
                  key={link.label}
                  href={link.href}
                  aria-label={link.label}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/20 text-[10px] font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                >
                  {link.label.slice(0, 2)}
                </a>
              ))}
            </div>
          </div>

          {footerColumns.map((column) => (
            <div key={column.title}>
              <h2 className="text-jp-xs font-semibold uppercase tracking-wide text-white">{column.title}</h2>
              <ul className="mt-2 space-y-1">
                {column.links.map((link) => (
                  <li key={link.href}>
                    <a
                      href={link.href}
                      className="text-jp-xs text-white/75 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    >
                      {link.label}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}

          <div>
            <h2 className="text-jp-xs font-semibold uppercase tracking-wide text-white">Stay Updated</h2>
            <p className="mt-2 text-jp-xs leading-relaxed text-white/75">
              Get the latest deals and travel updates delivered to your inbox.
            </p>
          </div>
        </div>
      </PageContainer>

      <div className="border-t border-white/15">
        <PageContainer className="flex flex-col gap-1 py-2.5 text-jp-xs text-white/70 sm:flex-row sm:items-center sm:justify-between">
          <p>© {new Date().getFullYear()} JetPakistan. All rights reserved.</p>
          <p className="inline-flex items-center gap-1">
            Made with <span aria-label="love">♥</span> in Pakistan
          </p>
        </PageContainer>
      </div>
    </footer>
  );
}
