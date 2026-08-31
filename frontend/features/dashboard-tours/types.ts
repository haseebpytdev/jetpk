export type TourStatus = "completed" | "skipped";

export type TourStateEntry = {
  status: TourStatus;
  at: string;
};

export type TourStep = {
  id: string;
  title: string;
  body: string;
  target: string | null;
};

export type TourPayload = {
  ok?: boolean;
  tour_key: string;
  tours: Record<string, TourStateEntry>;
  steps: TourStep[];
  should_auto_start: boolean;
};
