import nextDynamic from "next/dynamic";

export const revalidate = 60;
export const dynamic = "force-static";

const GroupsLandingPage = nextDynamic(
  () =>
    import("@/features/group-ticketing/components/GroupsLandingPage").then((m) => m.GroupsLandingPage),
  {
    loading: () => (
      <div data-testid="groups-landing-page" data-jp-groups-shell="1">
        <section className="relative overflow-hidden border-b border-jp-border" aria-labelledby="groups-hero-heading">
          <div className="absolute inset-0 bg-gradient-to-br from-[#0f3d2e] via-[#1a5c46] to-jp-page" aria-hidden="true" />
          <div className="relative mx-auto w-full max-w-jp-container px-jp-xl pb-14 pt-10">
            <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-white/80">GROUP TRAVEL MADE SIMPLE</p>
            <h1 id="groups-hero-heading" className="mt-2 text-3xl font-semibold tracking-[-0.035em] text-white sm:text-4xl">
              Find better group fares for your journey
            </h1>
          </div>
        </section>
      </div>
    ),
  },
);

/** Groups discovery landing — search + dynamic categories. Results at /groups/search. */
export default function GroupsHubPage() {
  return <GroupsLandingPage />;
}
