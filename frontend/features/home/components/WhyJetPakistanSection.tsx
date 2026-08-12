import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { VALUE_PROPOSITION_FIXTURES } from "../fixtures/inspiration";

export function WhyJetPakistanSection() {
  return (
    <SectionContainer>
      <PageContainer>
        <h2 className="font-display text-jp-h2 font-bold text-jp-text">Why JetPakistan</h2>
        <p className="mt-2 max-w-2xl text-jp-body text-jp-muted">
          Built for Pakistani travelers with transparent booking and dependable support.
        </p>

        <ul className="mt-jp-lg grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {VALUE_PROPOSITION_FIXTURES.map((item) => (
            <li
              key={item.id}
              className="rounded-jp-card border border-jp-border bg-jp-surface p-jp-lg shadow-jp-sm"
            >
              <span className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-jp-primary-soft text-jp-primary">
                <ValueIcon type={item.icon} />
              </span>
              <h3 className="mt-3 font-sans text-jp-md font-semibold text-jp-text">{item.title}</h3>
              <p className="mt-2 text-jp-sm leading-relaxed text-jp-muted">{item.description}</p>
            </li>
          ))}
        </ul>
      </PageContainer>
    </SectionContainer>
  );
}

function ValueIcon({ type }: { type: (typeof VALUE_PROPOSITION_FIXTURES)[number]["icon"] }) {
  const label = type.charAt(0).toUpperCase();
  return <span aria-hidden="true" className="text-jp-sm font-bold">{label}</span>;
}
