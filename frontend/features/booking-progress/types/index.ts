export type BookingProgressStepState = "completed" | "current" | "upcoming" | "skipped";

export type BookingProgressStep = {
  key: string;
  label: string;
  state: BookingProgressStepState;
  href?: string | null;
};

export type BookingProgressConfig = {
  steps: BookingProgressStep[];
  ariaLabel?: string;
};
