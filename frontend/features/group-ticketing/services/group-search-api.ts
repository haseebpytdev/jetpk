import type {
  GroupResultsPageResponse,
  GroupSearchDataResponse,
  GroupSearchFacetsResponse,
  GroupSearchFilters,
} from "../types";
import { buildGroupQuery, groupGet } from "./group-api-base";

/**
 * Public/read-only group search API.
 * Must not import auth/CSRF or booking/payment mutation modules.
 */

export async function fetchGroupSearchData(filters: GroupSearchFilters) {
  return groupGet<GroupSearchDataResponse>(`/groups/search/data?${buildGroupQuery(filters)}`);
}

export async function fetchGroupResultsPage(filters: GroupSearchFilters) {
  return groupGet<GroupResultsPageResponse>(`/groups/search/results?${buildGroupQuery(filters)}`);
}

export async function fetchGroupSearchFacets() {
  return groupGet<GroupSearchFacetsResponse>("/groups/search/facets");
}
