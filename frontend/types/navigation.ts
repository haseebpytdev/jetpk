export type NavLinkItem = {
  label: string;
  href: string;
  badge?: string;
  external?: boolean;
};

export type NavDropdownItem = {
  label: string;
  href: string;
  description?: string;
};

export type NavItem =
  | { type: "link"; label: string; href: string; badge?: string }
  | { type: "dropdown"; label: string; items: NavDropdownItem[] };

export type FooterColumn = {
  title: string;
  links: NavLinkItem[];
};

export type CurrencyOption = {
  code: string;
  label: string;
  symbol: string;
  flagLabel: string;
};
