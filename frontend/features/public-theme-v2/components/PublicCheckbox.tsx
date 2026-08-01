import type { InputHTMLAttributes, ReactNode } from "react";

type PublicCheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, "type"> & {
  label: ReactNode;
  id: string;
};

export function PublicCheckbox({ label, id, ...props }: PublicCheckboxProps) {
  return (
    <label htmlFor={id} className="jp-v2-check">
      <input type="checkbox" id={id} {...props} />
      <span>{label}</span>
    </label>
  );
}
