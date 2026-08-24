export type IntegrationCard = {
  code: string;
  name: string;
  category: string;
  categoryLabel?: string;
  icon: string;
  status: string;
  status_label?: string;
  environment?: string | null;
  configured?: boolean;
  active?: boolean;
  adapterInstalled?: boolean;
  supportsConnectionTest?: boolean;
  supportsTestTransaction?: boolean;
  supportsEnableToggle?: boolean;
  canActivateRuntime?: boolean;
  docsUrl?: string | null;
  manager?: string;
  summary?: Record<string, unknown>;
  needs_attention?: boolean;
};

export type HubPayload = {
  subtitle?: string;
  metrics?: { active?: number; configured?: number; needs_attention?: number; total?: number };
  categories?: Array<{ key: string; label: string }>;
  integrations?: IntegrationCard[];
  wizard?: {
    categories?: Array<{ key: string; label: string }>;
    providers?: IntegrationCard[];
    custom_api_activation_blocked?: boolean;
    custom_api_message?: string;
  };
};
