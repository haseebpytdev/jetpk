import { DASHBOARD_API_ROUTES, dashboardApiUrl } from "@/lib/read-only/laravel/api-base";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
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

type InboxPayload = {
  unreadCount?: number;
  items?: OpsInboxItem[];
  available?: boolean;
  transport?: string;
};

type EventsPayload = {
  cursor?: number;
  items?: OpsActivityEvent[];
  transport?: string;
  sinceId?: number;
};

export async function fetchOpsUnreadCount(): Promise<number> {
  const envelope = await fetchDashboardApi<{ unreadCount?: number }>(DASHBOARD_API_ROUTES.opsInboxUnread);
  return Number(envelope.data?.unreadCount ?? 0);
}

export async function fetchOpsInbox(): Promise<{ unreadCount: number; items: OpsInboxItem[] }> {
  const envelope = await fetchDashboardApi<InboxPayload>(DASHBOARD_API_ROUTES.opsInbox);
  return {
    unreadCount: Number(envelope.data?.unreadCount ?? 0),
    items: envelope.data?.items ?? [],
  };
}

export async function fetchOpsEvents(sinceId: number): Promise<{ cursor: number; items: OpsActivityEvent[] }> {
  const envelope = await fetchDashboardApi<EventsPayload>(DASHBOARD_API_ROUTES.opsEvents, {
    query: { since_id: sinceId, limit: 50 },
  });
  return {
    cursor: Number(envelope.data?.cursor ?? sinceId),
    items: envelope.data?.items ?? [],
  };
}

export async function fetchOpsWorkQueue(): Promise<OpsWorkQueuePayload> {
  const envelope = await fetchDashboardApi<OpsWorkQueuePayload>(DASHBOARD_API_ROUTES.opsWorkQueue);
  return (
    envelope.data ?? {
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
