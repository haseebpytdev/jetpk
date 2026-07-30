type AuthBrandHeaderProps = {
  eyebrow?: string;
  headline: string;
  headlineHighlight?: string;
  description?: string;
};

export function AuthBrandHeader({ eyebrow, headline, headlineHighlight, description }: AuthBrandHeaderProps) {
  return (
    <header data-testid="auth-brand-header">
      {eyebrow ? (
        <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-jp-brand">{eyebrow}</p>
      ) : null}
      <h2 className="mt-2 font-display text-jp-h2 font-bold leading-tight text-jp-text">
        {headline}
        {headlineHighlight ? (
          <>
            {" "}
            <span className="text-jp-brand">{headlineHighlight}</span>
          </>
        ) : null}
      </h2>
      {description ? <p className="mt-3 text-jp-sm leading-relaxed text-jp-muted">{description}</p> : null}
    </header>
  );
}
