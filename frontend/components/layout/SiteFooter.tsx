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
      <PageContainer className="py-jp-4xl">
        <div className="grid gap-10 lg:grid-cols-[1.4fr_repeat(4,minmax(0,1fr))]">
          <div className="space-y-5">
            <JetPakistanLogo variant="inverse" />
            <p className="max-w-sm text-jp-sm leading-relaxed text-white/80">
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
                  className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/20 text-jp-xs font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                >
                  {link.label.slice(0, 2)}
                </a>
              ))}
            </div>
          </div>

          {footerColumns.map((column) => (
            <div key={column.title}>
              <h2 className="text-jp-sm font-semibold uppercase tracking-wide text-white">{column.title}</h2>
              <ul className="mt-4 space-y-2">
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
        <PageContainer className="flex flex-col gap-3 py-5 text-jp-sm text-white/75 sm:flex-row sm:items-center sm:justify-between">
          <p>© {new Date().getFullYear()} JetPakistan. All rights reserved.</p>
          <p className="inline-flex items-center gap-1">
            Made with <span aria-label="love">♥</span> in Pakistan
          </p>
        </PageContainer>
      </div>
    </footer>
  );
}
