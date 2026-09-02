import { GroupSearchPage } from "@/features/group-ticketing";
import {
  fetchGroupSearchDataServer,
  fetchGroupSearchFacetsServer,
} from "@/features/group-ticketing/services/group-ticketing-api";
import type { GroupSearchFilters } from "@/features/group-ticketing/types";

type PageProps = {
  searchParams?: Promise<Record<string, string | string[] | undefined>>;
};

function first(value: string | string[] | undefined): string | undefined {
  if (Array.isArray(value)) return value[0];
  return value;
}

export default async function Page({ searchParams }: PageProps) {
  const raw = searchParams ? await searchParams : {};
  const filters: GroupSearchFilters = {
    airline: first(raw.airline) || undefined,
    sector: first(raw.sector) || undefined,
    date_from: first(raw.date_from) || undefined,
    category: first(raw.category) || undefined,
    page: Number(first(raw.page) ?? "1") || 1,
    sort: first(raw.sort) || undefined,
  };
  const hasSearch = Boolean(filters.airline || filters.sector || filters.date_from || filters.category);

  // Parallel server authority: facets + inventory before client hydration.
  const [initialFacets, initialResults] = await Promise.all([
    fetchGroupSearchFacetsServer(),
    hasSearch ? fetchGroupSearchDataServer(filters) : Promise.resolve(null),
  ]);

  return (
    <GroupSearchPage
      initialFilters={hasSearch ? filters : undefined}
      initialResults={initialResults}
      initialFacets={initialFacets}
    />
  );
}
