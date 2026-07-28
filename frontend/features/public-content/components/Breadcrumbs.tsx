import Link from "next/link";

type BreadcrumbItem = { label: string; href?: string };

type BreadcrumbsProps = {
  items: BreadcrumbItem[];
};

export function Breadcrumbs({ items }: BreadcrumbsProps) {
  return (
    <nav aria-label="Breadcrumb" className="text-jp-sm text-jp-muted">
      <ol className="flex flex-wrap items-center gap-2">
        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          return (
            <li key={`${item.label}-${index}`} className="inline-flex items-center gap-2">
              {index > 0 ? <span aria-hidden="true">/</span> : null}
              {isLast || !item.href ? (
                <span aria-current={isLast ? "page" : undefined} className={isLast ? "font-medium text-jp-text" : undefined}>
                  {item.label}
                </span>
              ) : (
                <Link href={item.href} className="hover:text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus">
                  {item.label}
                </Link>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
