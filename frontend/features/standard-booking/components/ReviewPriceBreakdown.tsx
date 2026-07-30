import type { AuthoritativePricing } from "../types/review-payment";
import { OrderSummary } from "@/features/booking-layout";

type ReviewPriceBreakdownProps = {
  pricing: AuthoritativePricing;
};

export function ReviewPriceBreakdown({ pricing }: ReviewPriceBreakdownProps) {
  return <OrderSummary pricing={pricing} testId="review-price-breakdown" />;
}
