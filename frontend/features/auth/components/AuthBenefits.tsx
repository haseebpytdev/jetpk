import type { AuthBenefitItem } from "../config/auth-benefits";

type AuthBenefitsProps = {
  items: AuthBenefitItem[];
  className?: string;
};

export function AuthBenefits({ items, className = "" }: AuthBenefitsProps) {
  return (
    <ul className={`space-y-4 ${className}`} data-testid="auth-benefits">
      {items.map((item) => (
        <li key={item.title} className="flex gap-3">
          <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-jp-brand-soft text-jp-brand">
            <BenefitIcon type={item.icon} />
          </span>
          <div>
            <p className="text-jp-sm font-semibold text-jp-text">{item.title}</p>
            <p className="mt-0.5 text-jp-xs text-jp-muted">{item.description}</p>
          </div>
        </li>
      ))}
    </ul>
  );
}

function BenefitIcon({ type }: { type: AuthBenefitItem["icon"] }) {
  const className = "h-5 w-5";
  switch (type) {
    case "ticket":
      return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
          <path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z" fill="none" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      );
    case "tag":
      return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
          <path d="M3 12 12 3l9 9-9 9-9-9Z" fill="none" stroke="currentColor" strokeWidth="1.75" />
          <circle cx="15.5" cy="8.5" r="1.5" fill="currentColor" />
        </svg>
      );
    case "clock":
      return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
          <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" strokeWidth="1.75" />
          <path d="M12 7v5l3 2" fill="none" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      );
    case "bolt":
      return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
          <path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z" fill="currentColor" />
        </svg>
      );
    case "users":
      return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
          <circle cx="9" cy="8" r="3" fill="none" stroke="currentColor" strokeWidth="1.75" />
          <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" fill="none" stroke="currentColor" strokeWidth="1.75" />
          <circle cx="17" cy="9" r="2.5" fill="none" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      );
    case "calendar":
      return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
          <rect x="3" y="5" width="18" height="16" rx="2" fill="none" stroke="currentColor" strokeWidth="1.75" />
          <path d="M8 3v4M16 3v4M3 10h18" fill="none" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      );
    default:
      return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
          <path d="M12 3 4 6v6c0 4.4 3.4 8.5 8 9 4.6-.5 8-4.6 8-9V6l-8-3Z" fill="none" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      );
  }
}
