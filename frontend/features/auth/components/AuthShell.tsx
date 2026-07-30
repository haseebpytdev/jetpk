import type { ReactNode } from "react";
import type { AuthBenefitItem } from "../config/auth-benefits";
import { LOGIN_BENEFITS } from "../config/auth-benefits";
import { AuthFormCard } from "./AuthFormCard";
import { AuthPageShell } from "./AuthPageShell";

type AuthShellProps = {
  title: string;
  description?: string;
  children: ReactNode;
  footer?: ReactNode;
  secondaryCard?: ReactNode;
  eyebrow?: string;
  headline?: string;
  headlineHighlight?: string;
  panelDescription?: string;
  benefits?: AuthBenefitItem[];
};

export function AuthShell({
  title,
  description,
  children,
  footer,
  secondaryCard,
  eyebrow = "JetPakistan",
  headline = "Welcome back to",
  headlineHighlight = "JetPakistan",
  panelDescription = "Sign in to manage bookings, payments, and traveler details securely.",
  benefits = LOGIN_BENEFITS,
}: AuthShellProps) {
  return (
    <AuthPageShell
      eyebrow={eyebrow}
      headline={headline}
      headlineHighlight={headlineHighlight}
      description={panelDescription}
      benefits={benefits}
    >
      <AuthFormCard title={title} description={description} footer={footer} secondaryCard={secondaryCard}>
        {children}
      </AuthFormCard>
    </AuthPageShell>
  );
}
