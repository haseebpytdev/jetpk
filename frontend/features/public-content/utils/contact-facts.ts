import type { ContactDetails } from "../types";

/** True when at least one public contact fact is configured for visible display / schema. */
export function hasVisibleContactFacts(contact: ContactDetails | null | undefined): boolean {
  if (!contact) return false;
  return Boolean(
    contact.phone?.trim() ||
      contact.email?.trim() ||
      contact.whatsapp?.trim() ||
      contact.office?.trim() ||
      contact.hours?.trim() ||
      contact.website?.trim(),
  );
}
