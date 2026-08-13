import { dashboardApiUrl, DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { laravelRequest } from "@/lib/api/laravel-action-client";

type Envelope<T> = { data?: T; message?: string };

async function mutate<T>(path: string, method: "POST" | "PATCH" | "DELETE", json?: Record<string, unknown>) {
  const result = await laravelRequest<Envelope<T>>(dashboardApiUrl(path), {
    method,
    json,
    retryCsrfOnce: true,
  });
  if (!result.ok) {
    throw new Error(result.message || "RBAC request failed");
  }
  return result.data;
}

export async function createRbacRole(payload: {
  name: string;
  slug?: string;
  agency_id: number;
  permission_keys: string[];
  description?: string;
}) {
  return mutate(DASHBOARD_API_ROUTES.roles, "POST", payload);
}

export async function updateRbacRole(
  id: string,
  payload: { name?: string; permission_keys?: string[]; description?: string },
) {
  return mutate(DASHBOARD_API_ROUTES.roleDetail(id), "PATCH", payload);
}

export async function cloneRbacRole(id: string, payload: { name: string; slug?: string; agency_id: number }) {
  return mutate(`${DASHBOARD_API_ROUTES.roleDetail(id)}/clone`, "POST", payload);
}

export async function deleteRbacRole(id: string) {
  return mutate(DASHBOARD_API_ROUTES.roleDetail(id), "DELETE");
}

export async function assignRbacRole(id: string, userId: number) {
  return mutate(`${DASHBOARD_API_ROUTES.roleDetail(id)}/assign`, "POST", { user_id: userId });
}

export async function unassignRbacRole(id: string, userId: number) {
  return mutate(`${DASHBOARD_API_ROUTES.roleDetail(id)}/unassign`, "POST", { user_id: userId });
}
