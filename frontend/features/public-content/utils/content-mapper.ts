export function resolveDestination(destination: string): string {
  if (!destination) return "#";
  if (destination.startsWith("route:")) {
    const route = destination.slice(6);
    const map: Record<string, string> = {
      home: "/",
      about: "/about-us",
      support: "/support",
      faq: "/faq",
      terms: "/terms",
      privacy: "/privacy",
      "booking.lookup": "/lookup-booking",
      "agent.register": "/agent/register",
      "home#jp-flight-search": "/#main-content",
    };
    return map[route] ?? "#";
  }
  if (destination.startsWith("http") || destination.startsWith("mailto:") || destination.startsWith("tel:")) {
    return destination;
  }
  if (destination.startsWith("/")) return destination;
  return `/${destination}`;
}

export function splitParagraphs(text: string): string[] {
  return text
    .split(/\r\n\r\n|\n\n/)
    .map((part) => part.trim())
    .filter(Boolean);
}

export function splitListLines(text: string): string[] {
  return text
    .split(/\r\n|\r|\n/)
    .map((line) => line.trim())
    .filter(Boolean);
}
