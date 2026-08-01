type PublicSectionHeadingProps = {
  title: string;
  eyebrow?: string;
  body?: string;
  id?: string;
};

export function PublicSectionHeading({ title, eyebrow, body, id }: PublicSectionHeadingProps) {
  return (
    <header className="jp-v2-section-heading">
      {eyebrow ? <p className="jp-v2-section-heading__eyebrow">{eyebrow}</p> : null}
      <h2 id={id} className="jp-v2-section-heading__title">
        {title}
      </h2>
      {body ? <p className="jp-v2-section-heading__body">{body}</p> : null}
    </header>
  );
}
