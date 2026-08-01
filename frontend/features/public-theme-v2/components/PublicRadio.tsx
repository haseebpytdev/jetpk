import type { InputHTMLAttributes, ReactNode } from "react";

type PublicRadioProps = Omit<InputHTMLAttributes<HTMLInputElement>, "type"> & {
  label: ReactNode;
  id: string;
  name: string;
};

export function PublicRadio({ label, id, name, ...props }: PublicRadioProps) {
  return (
    <label htmlFor={id} className="jp-v2-radio">
      <input type="radio" id={id} name={name} {...props} />
      <span>{label}</span>
    </label>
  );
}
