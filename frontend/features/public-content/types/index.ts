export type PublicContentSource = "cms" | "empty" | "fixture" | "laravel";

export type PublicSeo = {
  title: string;
  description: string;
  canonical?: string;
  robots?: string;
  og_title?: string;
  og_description?: string;
  og_image?: string | null;
};

export type ContactDetails = {
  phone: string;
  phone_e164: string;
  email: string;
  whatsapp: string;
  website: string;
  office: string;
  hours: string;
  company_legal_name: string;
};

export type PublicPageHero = {
  kicker?: string;
  title: string;
  description?: string;
};

export type ContentCard = {
  id: string;
  title: string;
  body: string;
  format?: "list" | "paragraphs";
};

export type PublicPageSection = {
  id: string;
  title: string;
  body?: string;
  items?: ContentCard[];
};

export type PublicPage = {
  pageKey: string;
  source: PublicContentSource;
  hero: PublicPageHero;
  sections: PublicPageSection[];
  seo: PublicSeo;
  contact?: ContactDetails;
  cta?: {
    primaryLabel?: string;
    primaryHref?: string;
    secondaryLabel?: string;
    secondaryHref?: string;
    label?: string;
    href?: string;
  };
};

export type FaqItem = {
  id: string;
  question: string;
  answer: string;
  categoryId: string;
};

export type FaqCategory = {
  id: string;
  title: string;
  items: FaqItem[];
};

export type FaqPageContent = {
  hero: PublicPageHero;
  categories: FaqCategory[];
  seo: PublicSeo;
  cta?: { label: string; href: string };
  source: PublicContentSource;
};

export type SupportTopic = {
  id: string;
  title: string;
  summary: string;
  category: string;
  keywords: string[];
};

export type SupportTicketCategoryOption = {
  value: string;
  label: string;
};

export type SupportPageContent = {
  hero: PublicPageHero;
  topics: SupportTopic[];
  departments: ContentCard[];
  seo: PublicSeo;
  contact: ContactDetails;
  faqTeaser?: { title: string; body?: string; linkLabel: string; linkHref: string };
  source: PublicContentSource;
};

export type LegalSection = {
  id: string;
  heading: string;
  body: string;
};

export type LegalDocument = {
  title: string;
  effectiveDate?: string;
  lastUpdated?: string;
  intro?: string;
  sections: LegalSection[];
  seo: PublicSeo;
  source: PublicContentSource;
};

export type CmsPublicPage = {
  slug: string;
  title: string;
  subtitle?: string;
  bodyHtml: string;
  seo: PublicSeo;
  source: PublicContentSource;
};

export type ContactFormPayload = {
  form_type: "contact" | "support";
  name?: string;
  email: string;
  subject?: string;
  category?: string;
  body: string;
  booking_reference?: string;
  website?: string;
};

export type ContactFormResponse =
  | { ok: true; ticket_reference: string }
  | { ok: false; message: string; fieldErrors?: Record<string, string[]>; status?: number };

export type LaravelManagedPageResponse = {
  page_key: string;
  source: "cms" | "empty";
  content: Record<string, unknown>;
  seo: PublicSeo;
  contact?: ContactDetails;
  sections_order?: string[];
};
