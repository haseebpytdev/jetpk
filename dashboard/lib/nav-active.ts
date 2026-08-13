export function navItemBase(href: string): string {
  const base = href.split("?")[0] ?? href;
  return base === "" ? "/" : base;
}

export function navItemMatchesPath(pathname: string, href: string): boolean {
  const path = navItemBase(pathname);
  const base = navItemBase(href);
  if (base === "/") {
    return path === "/" || path === "";
  }
  return path === base || path.startsWith(`${base}/`);
}

export function primaryActiveNavHref(pathname: string, hrefs: string[]): string | null {
  const matches = hrefs.filter((href) => navItemMatchesPath(pathname, href));
  if (matches.length === 0) {
    return null;
  }
  matches.sort((a, b) => navItemBase(b).length - navItemBase(a).length);
  return navItemBase(matches[0]);
}

export function isPrimaryActiveNav(pathname: string, href: string, allHrefs: string[]): boolean {
  const winner = primaryActiveNavHref(pathname, allHrefs);
  if (winner === null) {
    return false;
  }
  return navItemBase(href) === winner;
}
