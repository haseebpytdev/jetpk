import { PageContainer } from "@/components/layout/PageContainer";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";

export function SupportBanner() {
  return (
    <section className="border-y border-jp-border bg-gradient-to-r from-jp-primary-soft via-white to-sky-50">
      <PageContainer className="flex flex-col items-start justify-between gap-jp-lg py-jp-3xl sm:flex-row sm:items-center">
        <div className="max-w-2xl">
          <h2 className="font-display text-jp-h2 font-bold text-jp-text">Need help planning your trip?</h2>
          <p className="mt-2 text-jp-body text-jp-muted">
            Our support team is ready to assist with routes, group fares, and booking questions.
          </p>
        </div>
        <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
          <PrimaryButton>Contact Support</PrimaryButton>
          <SecondaryButton aria-label="WhatsApp support placeholder">WhatsApp</SecondaryButton>
        </div>
      </PageContainer>
    </section>
  );
}
