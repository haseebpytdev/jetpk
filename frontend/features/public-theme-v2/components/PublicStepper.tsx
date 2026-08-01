export type StepperStep = {
  id: string;
  label: string;
};

type PublicStepperProps = {
  steps: StepperStep[];
  currentStepId: string;
  includeSeats?: boolean;
};

export function PublicStepper({ steps, currentStepId, includeSeats = false }: PublicStepperProps) {
  const visibleSteps = includeSeats
    ? steps
    : steps.filter((step) => step.id !== "seats");

  const currentIndex = visibleSteps.findIndex((s) => s.id === currentStepId);

  return (
    <ol className="jp-v2-stepper" aria-label="Booking progress">
      {visibleSteps.map((step, index) => {
        const isActive = step.id === currentStepId;
        const isComplete = currentIndex >= 0 && index < currentIndex;

        return (
          <li
            key={step.id}
            className={[
              "jp-v2-stepper__step",
              isActive ? "jp-v2-stepper__step--active" : "",
              isComplete ? "jp-v2-stepper__step--complete" : "",
            ]
              .filter(Boolean)
              .join(" ")}
            aria-current={isActive ? "step" : undefined}
          >
            <span className="jp-v2-stepper__num" aria-hidden="true">
              {index + 1}
            </span>
            <span>{step.label}</span>
          </li>
        );
      })}
    </ol>
  );
}
