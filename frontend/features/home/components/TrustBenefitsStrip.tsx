import { cn } from "@/lib/cn";
import { BENEFIT_FIXTURES } from "../fixtures/benefits";

type TrustBenefitsStripProps = {
  className?: string;
};

function BenefitIcon({ type }: { type: (typeof BENEFIT_FIXTURES)[number]["icon"] }) {
  const paths: Record<(typeof BENEFIT_FIXTURES)[number]["icon"], string> = {
    shield: "M12 3 4 6v6c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V6l-8-3Z",
    headset: "M4 14a8 8 0 0 1 16 0v3a3 3 0 0 1-3 3h-1v-6h4M8 17H7a3 3 0 0 1-3-3v-1",
    fare: "M4 7h16v10H4z M8 11h8 M8 15h5",
    pakistan: "M12 3c-4 3-7 7-7 11a7 7 0 0 0 14 0c0-4-3-8-7-11Z",
  };

  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5 text-jp-primary" aria-hidden="true">
      <path
        d={paths[type]}
        fill="none"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function TrustBenefitsStrip({ className }: TrustBenefitsStripProps) {
  return (
    <ul className={cn("grid gap-3 sm:grid-cols-2 xl:grid-cols-4", className)}>
      {BENEFIT_FIXTURES.map((benefit) => (
        <li
          key={benefit.id}
          className="flex items-start gap-3 rounded-jp-md border border-jp-border-soft bg-jp-surface-muted/80 px-3 py-2.5"
        >
          <span className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-jp-primary-soft">
            <BenefitIcon type={benefit.icon} />
          </span>
          <span>
            <span className="block text-jp-sm font-semibold text-jp-text">{benefit.title}</span>
            <span className="block text-jp-xs text-jp-muted">{benefit.description}</span>
          </span>
        </li>
      ))}
    </ul>
  );
}
