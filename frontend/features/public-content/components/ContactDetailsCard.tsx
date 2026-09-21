import type { ContactDetails } from "../types";
import { hasVisibleContactFacts } from "../utils/contact-facts";

type ContactDetailsCardProps = {
  contact: ContactDetails;
  title?: string;
};

/**
 * Concise visible contact facts for AEO (phone, email, WhatsApp, office, hours).
 * Renders nothing when PublicConfig / SiteContact has no configured values.
 */
export function ContactDetailsCard({ contact, title = "Contact JetPakistan" }: ContactDetailsCardProps) {
  if (!hasVisibleContactFacts(contact)) {
    return null;
  }

  return (
    <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-card" aria-label={title}>
      <h2 className="text-jp-md font-semibold text-jp-text">{title}</h2>
      <dl className="mt-4 space-y-3 text-jp-sm text-jp-muted">
        {contact.phone ? (
          <div>
            <dt className="inline font-medium text-jp-text">Phone: </dt>
            <dd className="inline">
              <a
                href={`tel:${contact.phone_e164 || contact.phone.replace(/\D+/g, "")}`}
                className="text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
              >
                {contact.phone}
              </a>
            </dd>
          </div>
        ) : null}
        {contact.whatsapp ? (
          <div>
            <dt className="inline font-medium text-jp-text">WhatsApp: </dt>
            <dd className="inline">
              <a
                href={`https://wa.me/${contact.whatsapp}`}
                target="_blank"
                rel="noopener noreferrer"
                className="text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
              >
                {contact.whatsapp}
              </a>
            </dd>
          </div>
        ) : null}
        {contact.email ? (
          <div>
            <dt className="inline font-medium text-jp-text">Email: </dt>
            <dd className="inline">
              <a
                href={`mailto:${contact.email}`}
                className="text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
              >
                {contact.email}
              </a>
            </dd>
          </div>
        ) : null}
        {contact.website ? (
          <div>
            <dt className="inline font-medium text-jp-text">Website: </dt>
            <dd className="inline">
              <a
                href={contact.website}
                target="_blank"
                rel="noopener noreferrer"
                className="text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
              >
                {contact.website.replace(/^https?:\/\//, "")}
              </a>
            </dd>
          </div>
        ) : null}
        {contact.office ? (
          <div>
            <dt className="inline font-medium text-jp-text">Office: </dt>
            <dd className="inline">{contact.office}</dd>
          </div>
        ) : null}
        {contact.hours ? (
          <div>
            <dt className="inline font-medium text-jp-text">Hours: </dt>
            <dd className="inline">{contact.hours}</dd>
          </div>
        ) : null}
      </dl>
    </section>
  );
}
