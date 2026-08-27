import Link from "next/link";
import { PublicContainer } from "./PublicContainer";

const FOOTER_COLUMNS = [
  {
    title: "Travel",
    links: [
      { label: "Flight search", href: "/#flight-search" },
      { label: "Manage booking", href: "/lookup-booking" },
      { label: "Groups", href: "/groups" },
    ],
  },
  {
    title: "Company",
    links: [
      { label: "About us", href: "/about-us" },
      { label: "Contact", href: "/contact" },
      { label: "FAQ", href: "/faq" },
    ],
  },
  {
    title: "Legal",
    links: [
      { label: "Terms", href: "/terms" },
      { label: "Privacy", href: "/privacy" },
      { label: "Sitemap", href: "/sitemap" },
    ],
  },
] as const;

export function PublicFooterPrototype() {
  return (
    <footer className="jp-v2-footer">
      <PublicContainer>
        <div className="jp-v2-footer__grid">
          <div>
            <p className="jp-v2-footer__brand">JetPakistan</p>
            <p className="jp-v2-footer__tagline">
              Secure flight booking for Pakistan and beyond.
            </p>
          </div>
          {FOOTER_COLUMNS.map((col) => (
            <div key={col.title}>
              <p className="jp-v2-footer__col-title">{col.title}</p>
              <ul className="jp-v2-footer__links">
                {col.links.map((link) => (
                  <li key={link.href}>
                    <Link href={link.href}>{link.label}</Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        <p className="jp-v2-footer__copy">
          © {new Date().getFullYear()} JetPakistan. All rights reserved.
        </p>
      </PublicContainer>
    </footer>
  );
}
