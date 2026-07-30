import { ImageSlot } from "@/components/ui/ImageSlot";
import type { AuthBenefitItem } from "../config/auth-benefits";
import { AuthBenefits } from "./AuthBenefits";
import { AuthBrandHeader } from "./AuthBrandHeader";

const AUTH_ILLUSTRATION = "/images/auth/auth-illustration.svg";

type AuthIllustrationPanelProps = {
  eyebrow?: string;
  headline: string;
  headlineHighlight?: string;
  description?: string;
  benefits: AuthBenefitItem[];
};

export function AuthIllustrationPanel({
  eyebrow,
  headline,
  headlineHighlight,
  description,
  benefits,
}: AuthIllustrationPanelProps) {
  return (
    <div
      className="flex h-full flex-col rounded-jp-lg border border-jp-border bg-jp-surface-muted p-jp-lg lg:p-jp-xl"
      data-testid="auth-illustration-panel"
    >
      <AuthBrandHeader eyebrow={eyebrow} headline={headline} headlineHighlight={headlineHighlight} description={description} />
      <div className="mt-jp-lg overflow-hidden rounded-jp-md">
        <ImageSlot
          src={AUTH_ILLUSTRATION}
          alt=""
          decorative
          width={640}
          height={320}
          className="!max-w-none w-full"
          objectFit="cover"
          fallbackLabel="JetPakistan travel illustration"
        />
      </div>
      <div className="mt-jp-lg flex-1">
        <AuthBenefits items={benefits} />
      </div>
    </div>
  );
}
