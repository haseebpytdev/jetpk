export const AIRBLUE_ZAPWAYS_DEFAULT_PROTOCOL = "2.0" as const;
export type AirBlueZapwaysProtocolVersion = "2.0" | "3.0";

export const AIRBLUE_ZAPWAYS_V2_TEST_ENDPOINT = "https://otatest4.zapways.com/v2.0/OTAAPI.asmx";
export const AIRBLUE_ZAPWAYS_V2_LIVE_ENDPOINT = "https://ota4.zapways.com/v2.0/OTAAPI.asmx";
export const AIRBLUE_ZAPWAYS_V3_TEST_ENDPOINT = "https://otatest4.zapways.com/v3.0/OTAAPI.asmx";
export const AIRBLUE_ZAPWAYS_V3_LIVE_ENDPOINT = "https://ota4.zapways.com/v3.0/OTAAPI.asmx";

export const AIRBLUE_AUTO_CONNECTION_NAMES = {
  sandbox: "AirBlue Zapways TEST v2",
  live: "AirBlue Zapways LIVE v2",
  sandbox_v3: "AirBlue Zapways TEST v3",
  live_v3: "AirBlue Zapways LIVE v3",
} as const;

export function airBlueEndpointPreview(
  environment: string,
  protocol: AirBlueZapwaysProtocolVersion = AIRBLUE_ZAPWAYS_DEFAULT_PROTOCOL,
): string {
  const isLive = environment === "live";
  if (protocol === "3.0") {
    return isLive ? AIRBLUE_ZAPWAYS_V3_LIVE_ENDPOINT : AIRBLUE_ZAPWAYS_V3_TEST_ENDPOINT;
  }

  return isLive ? AIRBLUE_ZAPWAYS_V2_LIVE_ENDPOINT : AIRBLUE_ZAPWAYS_V2_TEST_ENDPOINT;
}

export function airBlueSuggestedConnectionName(
  environment: string,
  protocol: AirBlueZapwaysProtocolVersion = AIRBLUE_ZAPWAYS_DEFAULT_PROTOCOL,
): string {
  const isLive = environment === "live";
  if (protocol === "3.0") {
    return isLive ? AIRBLUE_AUTO_CONNECTION_NAMES.live_v3 : AIRBLUE_AUTO_CONNECTION_NAMES.sandbox_v3;
  }

  return isLive ? AIRBLUE_AUTO_CONNECTION_NAMES.live : AIRBLUE_AUTO_CONNECTION_NAMES.sandbox;
}

export function isAirBlueAutoConnectionName(name: string): boolean {
  const trimmed = name.trim();
  return (
    trimmed === AIRBLUE_AUTO_CONNECTION_NAMES.sandbox ||
    trimmed === AIRBLUE_AUTO_CONNECTION_NAMES.live ||
    trimmed === AIRBLUE_AUTO_CONNECTION_NAMES.sandbox_v3 ||
    trimmed === AIRBLUE_AUTO_CONNECTION_NAMES.live_v3
  );
}

export const AIRBLUE_MINIMAL_FIELD_KEYS = [
  "client_id",
  "client_key",
  "agent_id",
  "agent_password",
] as const;

/** Extended credential keys aligned with AirBlueSupplierConnectionNormalizer (offline contract). */
export const AIRBLUE_EXTENDED_FIELD_KEYS = [
  ...AIRBLUE_MINIMAL_FIELD_KEYS,
  "agent_type",
  "protocol_version",
  "certification_status",
  "search_certified",
] as const;
