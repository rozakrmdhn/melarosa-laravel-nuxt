// https://nuxt.com/docs/4.x/api/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2026-05-04',

  devServer: {
    port: Number(process.env.NUXT_PORT || process.env.PORT || 4000),
    host: process.env.NUXT_HOST || process.env.HOST || '0.0.0.0',
  },

  experimental: {
    appManifest: false,
  },

  vite: {
    server: {
      allowedHosts: [
        "localhost",
        "127.0.0.1",
        "dev-melarosa.saggaserv.my.id",
        ".saggaserv.my.id",
        ".my.id",
      ],
    },
    optimizeDeps: {
      include: ['maplibre-gl', 'ol'],
    },
    ssr: {
      // Externalize maplibre-gl and ol from SSR - they require browser APIs (WebGL, Canvas, DOM)
      external: ['maplibre-gl', 'ol'],
    },
  },

  /**
   * Manually disable nuxt telemetry.
   * @see [Nuxt Telemetry](https://github.com/nuxt/telemetry) for more information.
   */
  telemetry: {
    enabled: true,
  },

  $development: {
    ssr: true,
    devtools: {
      enabled: false,
    },
  },

  $production: {
    ssr: true,
  },

  app: {
    head: {
      htmlAttrs: {
        lang: 'id',
      },
      title: 'Home',
      titleTemplate: '%s · Melarosa GIS Bojonegoro',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover' },
        { name: 'theme-color', content: '#2B5B9E' },
        { name: 'robots', content: 'index, follow' },
        { property: 'og:site_name', content: 'MELAROSA Bojonegoro' },
        { property: 'og:locale', content: 'id_ID' },
        { name: 'twitter:card', content: 'summary_large_image' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' },
        { name: 'mobile-web-app-capable', content: 'yes' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:ital,wght@0,500;0,600;0,700;0,800;1,500&display=swap' },
      ],
    },
  },

  routeRules: {
    'auth/verify': { ssr: false },
    '/account': { redirect: '/admin/account/general' },
    '/account/**': { redirect: '/admin/account/**' },
    // Dataset WebGIS pages need client-only rendering
    '/admin/dataset/**': { ssr: false },
    '/admin/infrastruktur-segmen': { ssr: false },
    '/admin/infrastruktur-segmen/**': { ssr: false },
    '/admin/jaringan-jalan': { ssr: false },
    '/admin/jaringan-jalan/**': { ssr: false },
    '/admin/rekap-jalan': { ssr: false },
    '/admin/rekap-jalan/**': { ssr: false },
    '/maps': { ssr: false },
    '/maps/**': { ssr: false },
    // Dynamic proxy request API dan Storage ke backend Laravel
    '/api/v1/**': { proxy: `${process.env.API_LOCAL_URL || process.env.APP_URL || 'http://127.0.0.1:9000'}/api/v1/**` },
    '/sanctum/**': { proxy: `${process.env.API_LOCAL_URL || process.env.APP_URL || 'http://127.0.0.1:9000'}/sanctum/**` },
    '/storage/**': { proxy: `${process.env.API_LOCAL_URL || process.env.APP_URL || 'http://127.0.0.1:9000'}/storage/**` },
    // Dynamic proxy Martin Tile Server
    '/martin/**': { proxy: `${process.env.MARTIN_URL || 'http://127.0.0.1:9090'}/**` },
    // Dynamic proxy Basemap Rupabumi Indonesia (BIG)
    '/rbi-tiles/**': { proxy: 'https://geoservices.big.go.id/rbi/rest/services/BASEMAP/Rupabumi_Indonesia/MapServer/tile/**' },
    // Dynamic proxy Geopark Bojonegoro API
    '/api/geopark/diversity-sites': { proxy: 'https://geopark.bojonegorokab.go.id/api/diversity/list-diversity-site-list' },
  },

  css: ['~/assets/css/main.css'],

  /**
   * @see https://https://nuxt.com/docs/4.x/api/nuxt-config#modules-1
   */
  modules: [
    '@nuxt/ui',
    '@pinia/nuxt',
    'dayjs-nuxt',
    'nuxt-security',
  ],

  security: {
    headers: {
      strictTransportSecurity: process.env.NODE_ENV === 'production'
        ? { maxAge: 31536000, includeSubdomains: true }
        : false,
      crossOriginEmbedderPolicy: 'unsafe-none',
      crossOriginOpenerPolicy: 'same-origin-allow-popups',
      referrerPolicy: 'strict-origin-when-cross-origin',
      permissionsPolicy: {
        fullscreen: ['self'],
        geolocation: ['self'],
      },
      contentSecurityPolicy: {
        "upgrade-insecure-requests": process.env.NODE_ENV === 'production',
        "img-src": [
          "'self'",
          "data:",
          "blob:",
          "https://*.cartocdn.com",
          "https://*.arcgisonline.com",
          "https://*.openstreetmap.org",
          "https://*.opentopomap.org",
          "https://*.fastly.net",
          "https://*.bojonegorokab.go.id",
          "https://geoportal.bojonegorokab.go.id",
          "https://*.big.go.id",
          "https://*.meritech.cloud",
          "https://*.google.com",
          "https://*.my.id",
          "https:",
          process.env.APP_URL || 'http://127.0.0.1:9000',
          ...(process.env.NODE_ENV !== 'production' ? [
            process.env.MARTIN_URL || 'http://127.0.0.1:9090',
            'http://localhost:9090',
            'http://127.0.0.1:9090',
          ] : []),
        ],
        "worker-src": ["'self'", "blob:"],
        "frame-src": ["'self'", "blob:"],
        "object-src": ["'self'", "blob:"],
        "connect-src": [
          "'self'",
          "blob:",
          "https://*.cartocdn.com",
          "https://*.arcgisonline.com",
          "https://*.openstreetmap.org",
          "https://*.opentopomap.org",
          "https://*.fastly.net",
          "https://*.bojonegorokab.go.id",
          "https://geoportal.bojonegorokab.go.id",
          "https://*.big.go.id",
          "https://*.meritech.cloud",
          "https://*.google.com",
          "https://*.my.id",
          "wss://*.my.id",
          "https://tiles.openfreemap.org",
          "https://*.openfreemap.org",
          "https://s3.amazonaws.com",
          "https://elevation-tiles-prod.s3.amazonaws.com",
          "https://api.iconify.design",
          "https://api.simplesvg.com",
          "https://api.unisvg.com",
          process.env.APP_URL || 'http://127.0.0.1:9000',
          ...(process.env.NODE_ENV !== 'production' ? [
            process.env.MARTIN_URL || 'http://127.0.0.1:9090',
            "http://localhost:*",
            "http://127.0.0.1:*",
            "ws://localhost:*",
            "ws://127.0.0.1:*",
          ] : []),
        ],
      },
    },
  },

  dayjs: {
    locales: ['en'],
    plugins: ['relativeTime', 'utc', 'timezone'],
    defaultLocale: 'en',
    defaultTimezone: 'UTC',
  },

  typescript: {
    strict: false,
  },

  ui: {
    prose: true,
    content: true
  },

  colorMode: {
    preference: 'light',
    fallback: 'light',
  },

  /**
   * @see https://nuxt.com/docs/4.x/api/nuxt-config#runtimeconfig-1
   */
  runtimeConfig: {
    apiLocal: process.env.API_LOCAL_URL || process.env.APP_URL || 'http://localhost:9000',
    public: {
      apiBase: process.env.APP_URL || 'http://localhost:9000',
      apiPrefix: '/api/v1',
      storageBase: (process.env.APP_URL || 'http://localhost:9000') + '/storage/',
      martinUrl: process.env.NUXT_PUBLIC_MARTIN_URL || '/martin',
      rekapApiUrl: process.env.NUXT_PUBLIC_REKAP_API_URL || 'https://api-melarosa.saggaserv.my.id/api/v1',
      providers: {
        google: {
          name: "Google",
          icon: "",
          color: "neutral",
          variant: "soft",
        },
      },
    },
  },
})

