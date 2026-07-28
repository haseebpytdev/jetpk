import { PublicShell } from "@/components/layout/PublicShell";
import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { AnimatedFlightPath } from "@/components/motion/AnimatedFlightPath";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { getPublicSession } from "@/services/session";

export default async function HomePage() {
  const session = await getPublicSession();

  return (
    <PublicShell session={session}>
      <section className="border-b border-jp-border bg-gradient-to-b from-white to-jp-page">
        <PageContainer className="py-jp-4xl">
          <div className="max-w-3xl">
            <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-jp-primary">
              Public shell preview
            </p>
            <h1 className="mt-3 font-display text-jp-h1 font-bold leading-tight text-jp-text">
              Explore the world with <span className="text-jp-primary">JetPakistan</span>
            </h1>
            <p className="mt-4 max-w-2xl text-jp-body leading-relaxed text-jp-muted">
              Phase JP-FE-01 establishes the responsive public shell, design tokens, and navigation
              foundation. The full homepage and flight search interface arrive in JP-FE-02.
            </p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <PrimaryButton>Search Flights</PrimaryButton>
              <SecondaryButton>Contact Support</SecondaryButton>
            </div>
          </div>
          <AnimatedFlightPath className="mt-10" />
        </PageContainer>
      </section>

      <SectionContainer>
        <PageContainer>
          <div className="rounded-jp-card border border-jp-border bg-jp-surface p-jp-2xl shadow-jp-card">
            <h2 className="font-display text-jp-h3 font-semibold text-jp-text">Shell foundation ready</h2>
            <p className="mt-3 max-w-3xl text-jp-body text-jp-muted">
              Shared header, footer, account presentation states, currency selector, and design tokens are
              in place for upcoming public routes, booking flow pages, and customer experiences.
            </p>
            <ul className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {[
                "Responsive desktop and mobile navigation",
                "Accessible focus and keyboard support",
                "Laravel API boundary scaffold",
                "Fixture session adapter for auth UI states",
              ].map((item) => (
                <li
                  key={item}
                  className="rounded-jp-md border border-jp-border bg-jp-surface-muted px-4 py-3 text-jp-sm text-jp-text"
                >
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </PageContainer>
      </SectionContainer>
    </PublicShell>
  );
}
