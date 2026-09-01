import type { Metadata } from "next";
import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { Breadcrumbs, PublicPageHero, publicSeoToMetadata } from "@/features/public-content";

export async function generateMetadata(): Promise<Metadata> {
  return publicSeoToMetadata(
    {
      title: "Visa Services — JetPakistan",
      description: "Optional visa lookup services. Saudi visa lookup uses the official Saudi MOFA Visa Platform.",
      robots: "noindex,nofollow",
    },
    "/visa",
  );
}

export default function VisaLandingPage() {
  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "Visa Services" }]} />
      <div className="mt-jp-xl space-y-jp-2xl">
        <PublicPageHero
          hero={{
            kicker: "Visa Services",
            title: "Visa lookup",
            description: "Check visa status and view official visa documents. JetPakistan does not issue visas.",
          }}
          id="visa-landing-heading"
        />

        <div className="grid gap-jp-xl md:grid-cols-2">
          <article className="rounded-jp-xl border border-jp-border bg-jp-surface p-jp-2xl shadow-jp-card">
            <h2 className="text-jp-h3 font-semibold text-jp-text">Saudi Arabia</h2>
            <p className="mt-2 text-jp-sm text-jp-muted">
              Saudi Visa Lookup — check your Saudi visa status and view your visa document using the official Ministry of Foreign
              Affairs Visa Platform.
            </p>
            <Link
              href="/visa/saudi"
              className="mt-6 inline-flex rounded-jp-md bg-jp-accent px-4 py-2 text-jp-sm font-semibold text-white"
            >
              Saudi Visa Lookup
            </Link>
          </article>

          <article className="rounded-jp-xl border border-dashed border-jp-border bg-jp-surface/60 p-jp-2xl">
            <h2 className="text-jp-h3 font-semibold text-jp-muted">More countries</h2>
            <p className="mt-2 text-jp-sm text-jp-muted">Additional country providers can be added without changing OTA core.</p>
          </article>
        </div>
      </div>
    </PageContainer>
  );
}
