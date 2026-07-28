import type { GroupCategory } from "../types";

/** Fixture group categories aligned with JetPakistan group ticketing facets. */
export const GROUP_CATEGORY_FIXTURES: GroupCategory[] = [
  { slug: "all", label: "All" },
  { slug: "ksa", label: "KSA" },
  { slug: "uae", label: "UAE" },
  { slug: "muscat", label: "Muscat" },
];

export const GROUP_DESTINATION_FIXTURES = [
  "KSA — Jeddah",
  "KSA — Riyadh",
  "UAE — Dubai",
  "UAE — Abu Dhabi",
  "Muscat — Oman",
  "UK — London",
  "UK — Manchester",
] as const;
