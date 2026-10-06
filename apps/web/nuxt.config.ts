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
      // Externalize maplibre-gl and ol from SSR — they require browser APIs (WebGL, Canvas, DOM)
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
      title: 'Home',
      titleTemplate: '%s | Melarosa GIS Bojonegoro',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
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
    '/maps': { ssr: false },
    '/maps/**': { ssr: false },
    // Dynamic proxy request API dan Storage ke backend Laravel
    '/api/v1/**': { proxy: `${process.env.API_LOCAL_URL || process.env.APP_URL || 'http://127.0.0.1:9000'}/api/v1/**` },
    '/sanctum/**': { proxy: `${process.env.API_LOCAL_URL || process.env.APP_URL || 'http://127.0.0.1:9000'}/sanctum/**` },
    '/storage/**': { proxy: `${process.env.API_LOCAL_URL || process.env.APP_URL || 'http://127.0.0.1:9000'}/storage/**` },
    // Dynamic proxy Martin Tile Server
    '/martin/**': { proxy: `${process.env.MARTIN_URL || 'http://127.0.0.1:9090'}/**` },
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
      crossOriginEmbedderPolicy: 'unsafe-none',
      crossOriginOpenerPolicy: 'same-origin-allow-popups',
      referrerPolicy: 'strict-origin-when-cross-origin',
      permissionsPolicy: {
        fullscreen: ['self'],
        geolocation: ['self'],
      },
      contentSecurityPolicy: {
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
            "https://*.my.id",
            "https:",
            process.env.APP_URL || 'http://127.0.0.1:9000',
            process.env.MARTIN_URL || 'http://127.0.0.1:9090',
            'http://localhost:9090',
            'http://127.0.0.1:9090',
          ],
        "worker-src": ["'self'", "blob:"],
        "connect-src": [
            "'self'",
            "https://*.cartocdn.com",
            "https://*.arcgisonline.com",
            "https://*.openstreetmap.org",
            "https://*.opentopomap.org",
            "https://*.fastly.net",
            "https://*.bojonegorokab.go.id",
            "https://geoportal.bojonegorokab.go.id",
            "https://*.my.id",
            "wss://*.my.id",
            "https://api.iconify.design",
            "https://api.simplesvg.com",
            "https://api.unisvg.com",
            process.env.APP_URL || 'http://127.0.0.1:9000',
            process.env.MARTIN_URL || 'http://127.0.0.1:9090',
            ...(process.env.NODE_ENV !== 'production' ? [
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
