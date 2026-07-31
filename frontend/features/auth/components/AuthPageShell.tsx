import { PageContainer } from "@/components/layout/PageContainer";
import type { ReactNode } from "react";
import type { AuthBenefitItem } from "../config/auth-benefits";
import { AuthFormPanel } from "./AuthFormPanel";
import { AuthIllustrationPanel } from "./AuthIllustrationPanel";

type AuthPageShellProps = {
  eyebrow?: string;
  headline: string;
  headlineHighlight?: string;
  description?: string;
  benefits: AuthBenefitItem[];
  children: ReactNode;
};

export function AuthPageShell({
  eyebrow,
  headline,
  headlineHighlight,
  description,
  benefits,
  children,
}: AuthPageShellProps) {
  return (
    <PageContainer className="py-jp-lg sm:py-jp-xl">
      <div
        className="grid items-stretch gap-jp-lg lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-jp-2xl xl:max-w-[1060px] xl:grid-cols-[480px_1fr] xl:gap-jp-3xl"
        data-testid="auth-page-shell"
      >
        <div className="order-2 lg:order-1">
          <AuthIllustrationPanel
            eyebrow={eyebrow}
            headline={headline}
            headlineHighlight={headlineHighlight}
            description={description}
            benefits={benefits}
          />
        </div>
        <div className="order-1 lg:order-2">
          <AuthFormPanel>{children}</AuthFormPanel>
        </div>
      </div>
    </PageContainer>
  );
}
