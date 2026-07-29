export { FlightDetailsDrawer } from "./components/FlightDetailsDrawer";
export { useFlightDetails } from "./hooks/use-flight-details";
export { useRevalidation } from "./hooks/use-revalidation";
export { fetchOfferDetails } from "./services/flight-details-api";
export type {
  FlightDetailsContext,
  FlightOfferDetailsResponse,
  RevalidationState,
} from "./types";
export { isAllowedInternalHandoffUrl, providerRequiresRevalidation } from "./utils/handoff";
