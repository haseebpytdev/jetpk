import type { ContactDetails } from "../types";

type ContactDetailsCardProps = {
  contact: ContactDetails;
  title?: string;
};

export function ContactDetailsCard({ contact, title = "Contact JetPakistan" }: ContactDetailsCardProps) {
  return (
    <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-card" aria-label={title}>
      <h2 className="text-jp-md font-semibold text-jp-text">{title}</h2>
      <ul className="mt-4 space-y-3 text-jp-sm text-jp-muted">
        {contact.phone ? (
          <li>
            <span className="font-medium text-jp-text">Phone: </span>
            <a
              href={`tel:${contact.phone_e164 || contact.phone.replace(/\D+/g, "")}`}
              className="text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              {contact.phone}
            </a>
          </li>
        ) : null}
        {contact.whatsapp ? (
          <li>
            <span className="font-medium text-jp-text">WhatsApp: </span>
            <a
              href={`https://wa.me/${contact.whatsapp}`}
              target="_blank"
              rel="noopener noreferrer"
              className="text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              Chat on WhatsApp
            </a>
          </li>
        ) : null}
        {contact.email ? (
          <li>
            <span className="font-medium text-jp-text">Email: </span>
            <a
              href={`mailto:${contact.email}`}
              className="text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              {contact.email}
            </a>
          </li>
        ) : null}
        {contact.website ? (
          <li>
            <span className="font-medium text-jp-text">Website: </span>
            <a
              href={contact.website}
              target="_blank"
              rel="noopener noreferrer"
              className="text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              {contact.website.replace(/^https?:\/\//, "")}
            </a>
          </li>
        ) : null}
        {contact.office ? (
          <li>
            <span className="font-medium text-jp-text">Office: </span>
            <span>{contact.office}</span>
          </li>
        ) : null}
        {contact.hours ? (
          <li>
            <span className="font-medium text-jp-text">Hours: </span>
            <span>{contact.hours}</span>
          </li>
        ) : null}
      </ul>
    </section>
  );
}
