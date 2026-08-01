import type { ButtonHTMLAttributes, ReactNode } from "react";

type Variant = "primary" | "secondary" | "tertiary";

type PublicButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode;
  variant?: Variant;
  block?: boolean;
};

export function PublicButton({
  children,
  variant = "primary",
  block = false,
  className,
  type = "button",
  ...props
}: PublicButtonProps) {
  return (
    <button
      type={type}
      className={[
        "jp-v2-btn",
        `jp-v2-btn--${variant}`,
        block ? "jp-v2-btn--block" : "",
        className,
      ]
        .filter(Boolean)
        .join(" ")}
      {...props}
    >
      {children}
    </button>
  );
}
