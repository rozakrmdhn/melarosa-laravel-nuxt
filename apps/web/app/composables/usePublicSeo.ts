import { computed, unref, type MaybeRefOrGetter } from "vue";

export interface PublicSeoOptions {
  title: MaybeRefOrGetter<string>;
  description: MaybeRefOrGetter<string>;
  path: MaybeRefOrGetter<string>;
  image?: MaybeRefOrGetter<string | undefined>;
  type?: "website" | "article";
  jsonLd?: MaybeRefOrGetter<Record<string, any> | Record<string, any>[] | null | undefined>;
}

const SITE_ORIGIN = "https://melarosa.saggaserv.my.id";
const DEFAULT_OG_IMAGE = `${SITE_ORIGIN}/favicon-512.png`;

function resolveGetter<T>(val: MaybeRefOrGetter<T>): T {
  return typeof val === "function" ? (val as () => T)() : unref(val);
}

/**
 * Standar SEO untuk seluruh halaman publik & landing page MELAROSA:
 * - Meta title & description + Open Graph + Twitter Card
 * - Canonical URL (https://melarosa.saggaserv.my.id)
 * - Structured Data (JSON-LD) opsional
 */
export function usePublicSeo(options: PublicSeoOptions) {
  const canonicalUrl = computed(() => {
    const rawPath = String(resolveGetter(options.path) || "/");
    const normalized = rawPath.startsWith("/") ? rawPath : `/${rawPath}`;
    return `${SITE_ORIGIN}${normalized}`;
  });

  const resolvedImage = computed(() => {
    const img = options.image ? resolveGetter(options.image) : undefined;
    if (!img) return DEFAULT_OG_IMAGE;
    if (img.startsWith("http://") || img.startsWith("https://")) return img;
    return `${SITE_ORIGIN}${img.startsWith("/") ? "" : "/"}${img}`;
  });

  useSeoMeta({
    title: () => resolveGetter(options.title),
    description: () => resolveGetter(options.description),
    robots: "index, follow",
    ogType: options.type || "website",
    ogSiteName: "MELAROSA Bojonegoro",
    ogLocale: "id_ID",
    ogTitle: () => resolveGetter(options.title),
    ogDescription: () => resolveGetter(options.description),
    ogUrl: () => canonicalUrl.value,
    ogImage: () => resolvedImage.value,
    twitterCard: "summary_large_image",
    twitterTitle: () => resolveGetter(options.title),
    twitterDescription: () => resolveGetter(options.description),
    twitterImage: () => resolvedImage.value,
  });

  useHead(() => {
    const links = [{ rel: "canonical", href: canonicalUrl.value }];
    const ld = options.jsonLd ? resolveGetter(options.jsonLd) : null;
    const scripts = ld
      ? [
          {
            type: "application/ld+json",
            innerHTML: JSON.stringify(ld),
          },
        ]
      : [];

    return {
      link: links,
      script: scripts,
    };
  });
}
