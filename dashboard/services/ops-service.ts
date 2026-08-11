import { dashboardApiUrl, DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { laravelRequest } from "@/lib/api/laravel-action-client";

export type OpsInboxItem = {
  id: string;
  eventType: string;
  entityType: string;
  entityRef?: string | null;
  summary: string;
  actorName?: string | null;
  actorRole?: string | null;
  category?: string;
  createdAt?: string | null;
  readAt?: string | null;
  unread: boolean;
  deepLink?: string | null;
};

export type OpsActivityEvent = {
  id: number;
  publicId: string;
  occurredAt?: string | null;
  eventType: string;
  actorName: string;
  actorRole?: string | null;
  entityType: string;
  entityRef?: string | null;
  summary: string;
  deepLink?: string | null;
};

export type OpsWorkQueuePayload = {
  transport: string;
  bookings: Array<{
    id: number;
    reference: string | null;
    status: string;
    deepLink: string;
    entityType: string;
    assignedAt?: string | null;
  }>;
  supportTickets: Array<{
    id: number;
    reference: string | null;
    subject: string;
    status: string;
    deepLink: string;
    entityType: string;
  }>;
};

type Envelope<T> = { data?: T };

async function browserGetJson<T>(path: string): Promise<T> {
  const response = await fetch(dashboardApiUrl(path), {
    method: "GET",
    credentials: "same-origin",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  if (!response.ok) {
    throw new Error(`Ops API failed (${response.status})`);
  }
  return (await response.json()) as T;
}

export async function fetchOpsUnreadCount(): Promise<number> {
  const json = await browserGetJson<Envelope<{ unreadCount?: number }>>(DASHBOARD_API_ROUTES.opsInboxUnread);
  return Number(json.data?.unreadCount ?? 0);
}

export async function fetchOpsInbox(): Promise<{ unreadCount: number; items: OpsInboxItem[] }> {
  const json = await browserGetJson<Envelope<{ unreadCount?: number; items?: OpsInboxItem[] }>>(
    DASHBOARD_API_ROUTES.opsInbox,
  );
  return {
    unreadCount: Number(json.data?.unreadCount ?? 0),
    items: json.data?.items ?? [],
  };
}

export async function fetchOpsEvents(sinceId: number): Promise<{ cursor: number; items: OpsActivityEvent[] }> {
  const path = `${DASHBOARD_API_ROUTES.opsEvents}?since_id=${encodeURIComponent(String(sinceId))}&limit=50`;
  const json = await browserGetJson<Envelope<{ cursor?: number; items?: OpsActivityEvent[] }>>(path);
  return {
    cursor: Number(json.data?.cursor ?? sinceId),
    items: json.data?.items ?? [],
  };
}

export async function fetchOpsWorkQueue(): Promise<OpsWorkQueuePayload> {
  const json = await browserGetJson<Envelope<OpsWorkQueuePayload>>(DASHBOARD_API_ROUTES.opsWorkQueue);
  return (
    json.data ?? {
      transport: "EVENT_POLLING",
      bookings: [],
      supportTickets: [],
    }
  );
}

export async function markOpsInboxRead(ids: string[]): Promise<number> {
  const result = await laravelRequest<{ data?: { unreadCount?: number }; ok?: boolean }>(
    dashboardApiUrl(DASHBOARD_API_ROUTES.opsInboxRead),
    {
      method: "POST",
      json: { ids },
      retryCsrfOnce: true,
    },
  );
  if (!result.ok) {
    throw new Error(result.message || "Ops mark-read failed");
  }
  return Number(result.data?.data?.unreadCount ?? 0);
}
