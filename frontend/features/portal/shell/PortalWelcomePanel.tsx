import { LinkButton } from "@/components/ui/LinkButton";
import { cn } from "@/lib/cn";

export type PortalWelcomeAction = {
  href: string;
  label: string;
  variant?: "primary" | "secondary";
};

type PortalWelcomePanelProps = {
  eyebrow?: string;
  title: string;
  description: string;
  meta?: string[];
  actions?: PortalWelcomeAction[];
  className?: string;
  testId?: string;
};

/**
 * Compact authenticated welcome panel — decorative CSS motif only.
 * Media slot: optional future approved JetPakistan illustration can layer into
 * `.jp-portal-welcome__media` without changing the operational layout.
 */
export function PortalWelcomePanel({
  eyebrow,
  title,
  description,
  meta = [],
  actions = [],
  className,
  testId = "portal-welcome-panel",
}: PortalWelcomePanelProps) {
  return (
    <section
      className={cn(
        "jp-portal-welcome relative max-h-[13.75rem] overflow-hidden rounded-jp-lg border border-jp-border",
        "bg-gradient-to-br from-jp-brand-soft via-jp-surface to-jp-surface-muted",
        "px-4 py-4 sm:max-h-[14rem] sm:px-5 sm:py-5",
        className,
      )}
      data-testid={testId}
      aria-label="Welcome"
    >
      <div
        className="jp-portal-welcome__media pointer-events-none absolute inset-y-0 right-0 hidden w-[42%] max-w-[18rem] opacity-70 sm:block"
        aria-hidden="true"
        data-media-slot="portal-welcome-illustration"
      >
        <svg viewBox="0 0 320 180" className="h-full w-full" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="250" cy="70" r="54" stroke="currentColor" className="text-jp-brand/25" strokeWidth="1.5" />
          <circle cx="250" cy="70" r="28" stroke="currentColor" className="text-jp-brand/35" strokeWidth="1.5" />
          <path
            d="M20 130 C90 40, 170 150, 250 70"
            stroke="currentColor"
            className="text-jp-brand/40"
            strokeWidth="2"
            strokeDasharray="5 7"
            strokeLinecap="round"
          />
          <path
            d="M232 62 L268 78 L246 84 L240 104 Z"
            fill="currentColor"
            className="text-jp-brand/55"
          />
          <circle cx="250" cy="70" r="3.5" fill="currentColor" className="text-jp-brand" />
        </svg>
      </div>

      <div className="relative z-10 max-w-2xl space-y-3">
        {eyebrow ? (
          <p className="text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-jp-brand">{eyebrow}</p>
        ) : null}
        <div className="space-y-1.5">
          <h2 className="font-display text-jp-h3 font-semibold tracking-tight text-jp-text sm:text-jp-h2">{title}</h2>
          <p className="max-w-xl text-jp-sm font-normal leading-relaxed text-jp-muted">{description}</p>
        </div>
        {meta.length > 0 ? (
          <ul className="flex flex-wrap gap-x-3 gap-y-1 text-jp-xs text-jp-muted">
            {meta.map((item) => (
              <li key={item} className="rounded-jp-pill border border-jp-border/80 bg-jp-surface/70 px-2.5 py-1">
                {item}
              </li>
            ))}
          </ul>
        ) : null}
        {actions.length > 0 ? (
          <div className="flex flex-wrap gap-2 pt-1">
            {actions.map((action) => (
              <LinkButton
                key={`${action.href}:${action.label}`}
                href={action.href}
                variant={action.variant ?? "secondary"}
                className="!min-h-9 whitespace-nowrap !px-3 !py-1.5 text-jp-sm font-semibold"
              >
                {action.label}
              </LinkButton>
            ))}
          </div>
        ) : null}
      </div>
    </section>
  );
}

export function portalWelcomeFirstName(displayName: string): string {
  const trimmed = displayName.trim();
  if (!trimmed) return "there";
  return trimmed.split(/\s+/)[0] ?? trimmed;
}
