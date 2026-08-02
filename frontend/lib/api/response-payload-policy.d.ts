export type NormalizedHtmlPayload = {
  message: string;
  _html: true;
};

export function normalizeNonJsonPayload(
  contentType: string,
  bodyText: string,
  defaultMessageForStatus: (status: number) => string,
  status: number,
): unknown | NormalizedHtmlPayload | null;
