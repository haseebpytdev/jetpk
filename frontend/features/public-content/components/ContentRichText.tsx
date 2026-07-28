import { splitListLines, splitParagraphs } from "../utils/content-mapper";

type ContentRichTextProps = {
  body: string;
  format?: "list" | "paragraphs";
};

export function ContentRichText({ body, format = "paragraphs" }: ContentRichTextProps) {
  if (format === "list") {
    const items = splitListLines(body);
    return (
      <ul className="list-disc space-y-2 pl-5 text-jp-body text-jp-muted">
        {items.map((item) => (
          <li key={item}>{item}</li>
        ))}
      </ul>
    );
  }

  return (
    <div className="space-y-4 text-jp-body leading-relaxed text-jp-muted">
      {splitParagraphs(body).map((paragraph) => (
        <p key={paragraph}>{paragraph}</p>
      ))}
    </div>
  );
}
