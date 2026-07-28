import type { ContactDetails } from "../types";

/**
 * Canonical JetPakistan contact values from Laravel ClientGlobalContactResolver bootstrap.
 * Source: app/Support/Client/ClientPageBootstrapTemplate.php globalContent.contact
 *
 * Replace via SiteContactService Laravel API when CMS global settings are published.
 */
export const SITE_CONTACT_FIXTURE: ContactDetails = {
  phone: "0311 1222427",
  phone_e164: "+923111222427",
  email: "ota@jetpakistan.pk",
  whatsapp: "923111222427",
  website: "https://www.jetpakistan.com",
  office: "Office No. 220, 2nd Floor, Century Tower, Kalma Chowk, Gulberg III, Lahore",
  hours: "24/7",
  company_legal_name: "JetPakistan",
};
