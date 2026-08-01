import type { ReactNode } from "react";

type PublicSectionProps = {
  children: ReactNode;
  id?: string;
  "aria-labelledby"?: string;
  className?: string;
};

export function PublicSection({ children, id, "aria-labelledby": labelledBy, className }: PublicSectionProps) {
  return (
    <section
      id={id}
      aria-labelledby={labelledBy}
      className={["jp-v2-section", className].filter(Boolean).join(" ")}
    >
      {children}
    </section>
  );
}
