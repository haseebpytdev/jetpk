export const approvedHeroMedia = {
  url: "/images/home/hero-pakistan.jpg",
  alt: "Aircraft flying over a city skyline",
} as const;

type ApprovedMedia = {
  image: string;
  imageAlt: string;
};

const routeMediaByKey: Record<string, ApprovedMedia> = {
  dubai: {
    image: "/images/home/destination-dubai.jpg",
    imageAlt: "Dubai skyline at sunset",
  },
  dxb: {
    image: "/images/home/destination-dubai.jpg",
    imageAlt: "Dubai skyline at sunset",
  },
  jeddah: {
    image: "/images/home/destination-jeddah.jpg",
    imageAlt: "Jeddah Red Sea coastline",
  },
  jed: {
    image: "/images/home/destination-jeddah.jpg",
    imageAlt: "Jeddah Red Sea coastline",
  },
  london: {
    image: "/images/home/destination-london.jpg",
    imageAlt: "London cityscape along the Thames",
  },
  lhr: {
    image: "/images/home/destination-london.jpg",
    imageAlt: "London cityscape along the Thames",
  },
  istanbul: {
    image: "/images/home/destination-istanbul.jpg",
    imageAlt: "Istanbul skyline with historic architecture",
  },
  ist: {
    image: "/images/home/destination-istanbul.jpg",
    imageAlt: "Istanbul skyline with historic architecture",
  },
};

const offerMediaFallbacks: ApprovedMedia[] = [
  {
    image: "/images/home/offer-gcc.jpg",
    imageAlt: "Gulf city skyline at dusk",
  },
  {
    image: "/images/home/offer-uk.jpg",
    imageAlt: "United Kingdom travel destination",
  },
  {
    image: "/images/home/offer-domestic.jpg",
    imageAlt: "Pakistan domestic travel scenery",
  },
];

function normalizeMediaKey(value: string): string {
  return value.trim().toLowerCase();
}

const destinationMediaFallbacks: ApprovedMedia[] = [
  routeMediaByKey.dubai,
  routeMediaByKey.jeddah,
  routeMediaByKey.london,
  routeMediaByKey.istanbul,
];

export function resolveRouteMedia(
  route: {
    id: string;
    from: string;
    to: string;
    image?: string | null;
    imageAlt?: string;
  },
  index = 0,
): ApprovedMedia {
  if (route.image) {
    return {
      image: route.image,
      imageAlt: route.imageAlt ?? `${route.from} to ${route.to}`,
    };
  }

  for (const key of [route.id, route.to, route.from]) {
    const match = routeMediaByKey[normalizeMediaKey(key)];
    if (match) return match;
  }

  return destinationMediaFallbacks[index % destinationMediaFallbacks.length];
}

export function resolveOfferMedia(
  offer: { id: string; from: string; image?: string | null; imageAlt?: string },
  index: number,
): ApprovedMedia {
  if (offer.image) {
    return {
      image: offer.image,
      imageAlt: offer.imageAlt ?? offer.from,
    };
  }

  const byId = routeMediaByKey[normalizeMediaKey(offer.id)];
  if (byId) {
    return byId;
  }

  return offerMediaFallbacks[index % offerMediaFallbacks.length];
}

export const approvedHomepageMediaInventory = {
  hero: approvedHeroMedia,
  routes: Object.values(routeMediaByKey).filter(
    (item, index, items) => items.findIndex((candidate) => candidate.image === item.image) === index,
  ),
  offers: offerMediaFallbacks,
};
