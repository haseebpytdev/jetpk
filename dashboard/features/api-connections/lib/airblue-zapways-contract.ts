export const AIRBLUE_ZAPWAYS_V2_TEST_ENDPOINT = "https://otatest4.zapways.com/v2.0/OTAAPI.asmx";
export const AIRBLUE_ZAPWAYS_V2_LIVE_ENDPOINT = "https://ota4.zapways.com/v2.0/OTAAPI.asmx";

export const AIRBLUE_AUTO_CONNECTION_NAMES = {
  sandbox: "AirBlue Zapways TEST v2",
  live: "AirBlue Zapways LIVE v2",
} as const;

export function airBlueEndpointPreview(environment: string): string {
  return environment === "live" ? AIRBLUE_ZAPWAYS_V2_LIVE_ENDPOINT : AIRBLUE_ZAPWAYS_V2_TEST_ENDPOINT;
}

export function airBlueSuggestedConnectionName(environment: string): string {
  return environment === "live" ? AIRBLUE_AUTO_CONNECTION_NAMES.live : AIRBLUE_AUTO_CONNECTION_NAMES.sandbox;
}

export function isAirBlueAutoConnectionName(name: string): boolean {
  const trimmed = name.trim();
  return trimmed === AIRBLUE_AUTO_CONNECTION_NAMES.sandbox || trimmed === AIRBLUE_AUTO_CONNECTION_NAMES.live;
}

export const AIRBLUE_MINIMAL_FIELD_KEYS = ["client_id", "client_key", "agent_id", "agent_password"] as const;
