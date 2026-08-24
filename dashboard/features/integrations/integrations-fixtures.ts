import type { IntegrationCard, HubPayload } from "@/features/integrations/integrations-types";

export type { IntegrationCard, HubPayload };

/** Synthetic preview fixtures for Integrations Hub (no secrets). */
export function buildIntegrationsFixture(category = "all"): {
  hub: HubPayload;
  permissions: Record<string, boolean>;
} {
  const all: IntegrationCard[] = [
    {
      code: "sabre",
      name: "Sabre",
      category: "flights",
      categoryLabel: "Flights",
      icon: "SB",
      status: "connected",
      status_label: "Connected",
      environment: "live",
      configured: true,
      active: true,
      adapterInstalled: true,
      supportsConnectionTest: true,
      supportsTestTransaction: false,
      supportsEnableToggle: true,
      canActivateRuntime: true,
      docsUrl: "https://developer.sabre.com/",
      summary: {
        environment: "live",
        configured: true,
        is_active: true,
        last_test_status: "ready_for_review",
        connection_count: 2,
        supports_multiple_connections: true,
      },
      manager: "supplier",
    },
    {
      code: "iati",
      name: "IATI",
      category: "flights",
      categoryLabel: "Flights",
      icon: "IA",
      status: "degraded",
      status_label: "Degraded",
      environment: "test",
      configured: true,
      active: true,
      adapterInstalled: true,
      supportsConnectionTest: true,
      supportsTestTransaction: false,
      supportsEnableToggle: true,
      canActivateRuntime: true,
      summary: { environment: "test", configured: true, last_error: "provider timeout (sanitized)" },
      needs_attention: true,
    },
    {
      code: "abhipay",
      name: "AbhiPay",
      category: "payments",
      categoryLabel: "Payments",
      icon: "AP",
      status: "connected",
      status_label: "Connected",
      environment: "test",
      configured: true,
      active: true,
      adapterInstalled: true,
      supportsConnectionTest: true,
      supportsTestTransaction: true,
      supportsEnableToggle: true,
      canActivateRuntime: true,
      docsUrl: "/docs/payments/abhipay-integration.md",
      summary: {
        environment: "test",
        credentials_configured: true,
        merchant_secret_configured: true,
        merchant_secret_masked: "•••••••••• configured",
        merchant_id_masked: "••••CH01",
        callback_url: "https://jetpakistan.pk/payments/abhipay/callback",
        base_url: "https://api.abhipay.com.pk/api/v3",
        is_active: true,
        checkout_available: true,
      },
    },
    {
      code: "hotelbeds",
      name: "Hotelbeds",
      category: "hotels",
      categoryLabel: "Hotels",
      icon: "HB",
      status: "not_configured",
      status_label: "Not configured",
      configured: false,
      active: false,
      adapterInstalled: false,
      supportsConnectionTest: false,
      supportsTestTransaction: false,
      supportsEnableToggle: false,
      canActivateRuntime: false,
      summary: { configured: false, can_activate_runtime: false },
    },
  ];

  const filtered = category === "all" ? all : all.filter((card) => card.category === category);

  return {
    permissions: {
      view: true,
      manage: true,
      test: true,
      activate: true,
      test_payment: true,
      audit: true,
    },
    hub: {
      subtitle: "Configure, test and monitor every external service connected to JetPakistan.",
      metrics: {
        active: all.filter((c) => c.active).length,
        configured: all.filter((c) => c.configured).length,
        needs_attention: all.filter((c) => c.needs_attention).length,
        total: all.length,
      },
      categories: [
        { key: "all", label: "All" },
        { key: "flights", label: "Flights" },
        { key: "payments", label: "Payments" },
        { key: "hotels", label: "Hotels" },
      ],
      integrations: filtered,
      wizard: {
        categories: [
          { key: "flights", label: "Flights" },
          { key: "payments", label: "Payments" },
          { key: "hotels", label: "Hotels" },
          { key: "messaging", label: "Messaging" },
          { key: "other", label: "Other" },
        ],
        providers: all,
        custom_api_activation_blocked: true,
        custom_api_message:
          "A generic API configured in Dashboard does not automatically become a functional flight supplier or payment gateway. A JetPakistan runtime adapter is still required.",
      },
    },
  };
}

export function buildIntegrationDetailFixture(code: string): Record<string, unknown> {
  const { hub } = buildIntegrationsFixture("all");
  const card = hub.integrations?.find((row) => row.code === code) ?? hub.integrations?.[0];
  const isAbhiPay = code === "abhipay";
  const isLiveAbhiPay = false;

  return {
    ...card,
    settings: {
      values: isAbhiPay
        ? {
            environment: isLiveAbhiPay ? "live" : "test",
            is_active: true,
            base_url: "https://api.abhipay.com.pk/api/v3",
            merchant_secret_configured: true,
            merchant_secret_masked: "•••••••••• configured",
            callback_url: "https://jetpakistan.pk/payments/abhipay/callback",
            success_url: "",
            cancel_url: "",
            decline_url: "",
          }
        : card?.summary ?? {},
    },
    health: {
      status: card?.status ?? "never_tested",
      history: [
        {
          id: 1,
          test_type: "connection",
          status: "healthy",
          latency_ms: 215,
          tested_at: "2026-08-22T12:00:00.000Z",
          sanitized_message: "Credential readiness check passed (read-only).",
        },
        {
          id: 2,
          test_type: "test_payment",
          status: "healthy",
          tested_at: "2026-08-22T12:05:00.000Z",
          sanitized_message: "Diagnostic test payment created (test mode).",
        },
      ],
    },
    supports_test_transaction: Boolean(card?.supportsTestTransaction),
  };
}
