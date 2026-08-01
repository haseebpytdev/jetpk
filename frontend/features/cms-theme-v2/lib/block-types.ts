export type CmsImageRef = {
  src: string;
  alt: string;
  width?: number;
  height?: number;
};

export type CmsAction = {
  label: string;
  href: string;
};

export type CmsBlock =
  | {
      type: "hero";
      eyebrow?: string;
      heading: string;
      body?: string;
      image?: CmsImageRef;
      actions?: CmsAction[];
    }
  | { type: "richText"; html: string }
  | {
      type: "image";
      image: CmsImageRef;
      caption?: string;
      width?: "container" | "wide" | "full";
    }
  | {
      type: "cardGrid";
      heading?: string;
      body?: string;
      columns?: 2 | 3 | 4;
      items: Array<{
        icon?: string;
        heading: string;
        body?: string;
        href?: string;
      }>;
    }
  | {
      type: "stats";
      heading?: string;
      items: Array<{ value: string; label: string; icon?: string }>;
    }
  | {
      type: "timeline";
      heading?: string;
      items: Array<{ marker: string; heading: string; body?: string }>;
    }
  | {
      type: "faq";
      heading?: string;
      items: Array<{ question: string; answer: string }>;
    }
  | {
      type: "callout";
      tone?: "info" | "success" | "warning" | "danger";
      heading?: string;
      body: string;
      action?: CmsAction;
    }
  | {
      type: "gallery";
      heading?: string;
      columns?: 2 | 3 | 4;
      items: CmsImageRef[];
    }
  | {
      type: "section";
      heading?: string;
      body?: string;
      blocks?: CmsBlock[];
    };

export type CmsPageTemplate =
  | "default-content"
  | "hero-content"
  | "landing"
  | "faq"
  | "contact"
  | "policy"
  | "destination"
  | "offer"
  | "article-index"
  | "article-detail";

export type CmsPagePayload = {
  title?: string;
  template?: string;
  pageKey?: string;
  slug?: string;
  routeFamily?: string;
  blocks: CmsBlock[];
};
