"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { navGroups } from "@/lib/nav-config";
import { cn } from "@/lib/utils";
import { mockUser } from "@/mocks/overview-fixtures";

type Props = {
  open: boolean;
  onClose: () => void;
};

function isActive(pathname: string, href: string): boolean {
  if (href === "/") {
    return pathname === "/" || pathname === "";
  }
  const base = href.split("?")[0];
  return pathname === base || pathname.startsWith(`${base}/`);
}

export function DashboardSidebar({ open, onClose }: Props) {
  const pathname = usePathname();

  return (
    <>
      <div
        className={cn(
          "fixed inset-0 z-40 bg-black/40 transition-opacity duration-drawer lg:hidden",
          open ? "opacity-100" : "pointer-events-none opacity-0",
        )}
        aria-hidden={!open}
        onClick={onClose}
      />
      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex w-[min(100%,280px)] flex-col bg-jp-sidebar text-white transition-transform duration-drawer motion-reduce:transition-none lg:static lg:z-auto lg:shrink-0 lg:translate-x-0",
          open ? "translate-x-0" : "-translate-x-full lg:translate-x-0",
        )}
        aria-label="Dashboard navigation"
      >
        <div className="border-b border-white/10 p-5">
          <Link href="/" className="block" onClick={onClose}>
            <span className="font-display text-lg font-bold tracking-tight">JetPakistan</span>
            <span className="mt-1 block text-xs uppercase tracking-widest text-emerald-400/90">
              Fly smart, fly easy
            </span>
          </Link>
          <div className="mt-4 flex items-center gap-3 rounded-xl bg-white/5 p-3">
            <span className="flex h-11 w-11 items-center justify-center rounded-full bg-jp-accent text-sm font-semibold">
              {mockUser.initials}
            </span>
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold">{mockUser.name}</p>
              <p className="truncate text-xs text-gray-400">{mockUser.role}</p>
            </div>
            <span className="ml-auto h-2.5 w-2.5 rounded-full bg-jp-accent" title="Online" />
          </div>
        </div>
        <nav className="flex-1 overflow-y-auto p-3">
          {navGroups.map((group) => (
            <div key={group.label} className="mb-4 last:mb-0">
              <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-widest text-gray-500">
                {group.label}
              </p>
              <ul className="space-y-1">
                {group.items.map((item) => {
                  const active = isActive(pathname, item.href);
                  return (
                    <li key={`${group.label}-${item.label}`}>
                      <Link
                        href={item.href}
                        onClick={onClose}
                        className={cn(
                          "flex min-h-11 items-center gap-2 rounded-xl px-3 py-2 text-sm transition-colors duration-ui focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent",
                          active
                            ? "bg-jp-accent font-medium text-white"
                            : "text-gray-300 hover:bg-white/10 hover:text-white",
                        )}
                        aria-current={active ? "page" : undefined}
                      >
                        <span className="flex-1">{item.label}</span>
                        {item.planned ? (
                          <span className="rounded-md bg-white/10 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">
                            Planned
                          </span>
                        ) : null}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          ))}
        </nav>
        <div className="border-t border-white/10 p-4">
          <div className="rounded-xl bg-white/5 p-4">
            <p className="text-sm font-semibold">Need Help?</p>
            <p className="mt-1 text-xs text-gray-400">Preview support callout — no live ticket created.</p>
            <button
              type="button"
              className="mt-3 min-h-11 w-full rounded-xl bg-jp-accent px-3 py-2 text-sm font-medium text-white hover:bg-jp-accent-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
              onClick={() => alert("Preview only — contact support is not connected.")}
            >
              Contact Support
            </button>
          </div>
        </div>
      </aside>
    </>
  );
}
