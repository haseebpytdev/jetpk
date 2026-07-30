type AuthFooterLink = {
  label: string;
  href: string;
};

type AuthFooterLinksProps = {
  prompt: string;
  links: AuthFooterLink[];
};

export function AuthFooterLinks({ prompt, links }: AuthFooterLinksProps) {
  return (
    <p className="text-center text-jp-sm text-jp-muted" data-testid="auth-footer-links">
      {prompt}{" "}
      {links.map((link, index) => (
        <span key={link.href}>
          {index > 0 ? " · " : null}
          <a href={link.href} className="font-semibold text-jp-primary hover:underline focus-visible:shadow-jp-focus">
            {link.label}
          </a>
        </span>
      ))}
    </p>
  );
}
