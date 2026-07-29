"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { GroupTicketingForm } from "@/features/search/components/GroupTicketingForm";
import { validateGroupSearch } from "@/features/search/utils/validation";
import { fetchGroupResultsPage, fetchGroupSearchData } from "../services/group-ticketing-api";
import type { GroupPackage, GroupSearchFilters } from "../types";
import { GroupEmptyResultsState } from "./GroupStateCards";
import { GroupLockedState } from "./GroupStateCards";
import { GroupResultCard } from "./GroupResultCard";
import { PrimaryButton } from "@/components/ui/PrimaryButton";

function parseFilters(params: URLSearchParams): GroupSearchFilters {
  return {
    sector: params.get("sector") ?? undefined,
    date_from: params.get("date_from") ?? undefined,
    category: params.get("category") ?? undefined,
    page: Number(params.get("page") ?? "1") || 1,
    sort: params.get("sort") ?? undefined,
  };
}

export function GroupSearchPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const params = useMemo(() => new URLSearchParams(searchParams.toString()), [searchParams]);
  const filters = useMemo(() => parseFilters(params), [params]);
  const hasSearch = Boolean(filters.sector || filters.date_from || filters.category);

  const [sector, setSector] = useState(filters.sector ?? "");
  const [category, setCategory] = useState(filters.category ?? "all");
  const [travelDate, setTravelDate] = useState(filters.date_from ?? "");
  const [errors, setErrors] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  const [cards, setCards] = useState<GroupPackage[]>([]);
  const [countLabel, setCountLabel] = useState("");
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [userNotice, setUserNotice] = useState<string | null>(null);
  const [locked, setLocked] = useState(false);
  const [lockedMessage, setLockedMessage] = useState<string | undefined>();
  const [page, setPage] = useState(filters.page ?? 1);
  const [hasMore, setHasMore] = useState(false);
  const [sectors, setSectors] = useState<string[]>([]);

  const loadResults = useCallback(async (nextFilters: GroupSearchFilters, append = false) => {
    setLoading(true);
    const response = hasSearch || nextFilters.sector || nextFilters.date_from
      ? await fetchGroupSearchData(nextFilters)
      : await fetchGroupSearchData({ page: 1 });

    if (!response.ok) {
      setErrors([response.message]);
      setLoading(false);
      return;
    }

    setSectors(response.data.facets.sectors);
    setCards((current) => (append ? [...current, ...response.data.cards] : response.data.cards));
    setCountLabel(response.data.count_label);
    setStatusMessage(response.data.status_message ?? null);
    setUserNotice(response.data.user_notice ?? null);
    setLocked(response.data.lock_state.locked);
    setLockedMessage(response.data.lock_state.message ?? undefined);
    setPage(response.data.page);
    setHasMore(response.data.has_more);
    setLoading(false);
  }, [hasSearch]);

  useEffect(() => {
    if (hasSearch) {
      void loadResults(filters, false);
    } else {
      void fetchGroupSearchData({ page: 1 }).then((response) => {
        if (response.ok) setSectors(response.data.facets.sectors);
      });
    }
  }, [filters, hasSearch, loadResults]);

  const handleSubmit = () => {
    const result = validateGroupSearch({ sector, category, travelDate });
    if (!result.valid) {
      setErrors(result.errors);
      return;
    }

    const next = new URLSearchParams();
    if (sector) next.set("sector", sector);
    if (travelDate) next.set("date_from", travelDate);
    if (category && category !== "all") next.set("category", category);
    router.push(`/groups/search?${next.toString()}`);
  };

  const handleLoadMore = async () => {
    const nextPage = page + 1;
    const response = await fetchGroupResultsPage({ ...filters, page: nextPage });
    if (!response.ok) {
      setErrors([response.message]);
      return;
    }
    setCards((current) => [...current, ...response.data.cards]);
    setPage(response.data.page);
    setHasMore(response.data.has_more);
    setCountLabel(response.data.count_label);
  };

  if (locked) {
    return (
      <div className="ota-container py-8">
        <GroupLockedState message={lockedMessage} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <header className="mb-6">
        <h1 className="text-2xl font-semibold text-jp-text">Group Ticketing</h1>
        <p className="mt-1 text-jp-sm text-jp-muted">Search sector, travel date, and category. Passenger counts are collected during booking.</p>
      </header>

      <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
        <GroupTicketingForm
          sector={sector}
          category={category}
          travelDate={travelDate}
          sectors={sectors}
          onSectorChange={setSector}
          onCategoryChange={setCategory}
          onTravelDateChange={setTravelDate}
          onSubmit={handleSubmit}
          errors={errors}
          disabled={loading}
        />
        {sectors.length > 0 ? (
          <p className="mt-3 text-jp-xs text-jp-muted">Sectors loaded from live inventory ({sectors.length} available).</p>
        ) : null}
      </div>

      {hasSearch ? (
        <section className="mt-8 space-y-4" aria-live="polite">
          <div className="flex items-center justify-between gap-3">
            <h2 className="text-lg font-semibold text-jp-text">Results</h2>
            <p className="text-jp-sm text-jp-muted">{countLabel}</p>
          </div>
          {userNotice ? <p className="rounded-jp-md border border-amber-200 bg-amber-50 px-3 py-2 text-jp-sm text-amber-900">{userNotice}</p> : null}
          {statusMessage && cards.length === 0 ? <GroupEmptyResultsState /> : null}
          {cards.map((card) => (
            <GroupResultCard key={`${card.public_id ?? card.id}`} card={card} />
          ))}
          {hasMore ? (
            <div className="flex justify-center">
              <PrimaryButton onClick={() => void handleLoadMore()} disabled={loading}>
                Load more
              </PrimaryButton>
            </div>
          ) : null}
        </section>
      ) : null}
    </div>
  );
}
