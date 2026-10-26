function escapeXml(unsafe: string): string {
  return unsafe
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

export default defineEventHandler((event) => {
  // Cache response for 24 hours on CDN/Proxy, stale-while-revalidate 7 days
  setHeader(event, "Content-Type", "image/svg+xml; charset=utf-8");
  setHeader(event, "Cache-Control", "public, max-age=86400, s-maxage=86400, stale-while-revalidate=604800");

  const query = getQuery(event);

  // Parse parameters
  const rawLat = Number(query.lat);
  const rawLng = Number(query.lng);
  const rawZ = Number(query.z);

  const lat = !isNaN(rawLat) && rawLat >= -90 && rawLat <= 90 ? rawLat : -7.1502;
  const lng = !isNaN(rawLng) && rawLng >= -180 && rawLng <= 180 ? rawLng : 111.8817;
  const zoom = !isNaN(rawZ) && rawZ >= 1 && rawZ <= 22 ? Math.round(rawZ * 10) / 10 : 13.5;

  const rawWilayah = typeof query.wilayah === "string" ? query.wilayah.trim() : "";
  const rawLabel = typeof query.label === "string" ? query.label.trim() : "";
  const basemap = typeof query.basemap === "string" && query.basemap.trim() ? query.basemap.trim().toUpperCase() : "ESRI";

  let title = "Peta Spasial Bojonegoro";
  let subtitle = "Wilayah Kabupaten Bojonegoro, Jawa Timur";

  if (rawWilayah) {
    title = `Kecamatan ${rawWilayah}`;
    subtitle = "Wilayah Administrasi Kabupaten Bojonegoro";
  } else if (rawLabel) {
    title = rawLabel;
    subtitle = "Lokasi Tematik WebGIS Bojonegoro";
  }

  const safeTitle = escapeXml(title);
  const safeSubtitle = escapeXml(subtitle);
  const safeBasemap = escapeXml(basemap);
  const formattedLat = lat >= 0 ? `${lat.toFixed(5)}° N` : `${Math.abs(lat).toFixed(5)}° S`;
  const formattedLng = lng >= 0 ? `${lng.toFixed(5)}° E` : `${Math.abs(lng).toFixed(5)}° W`;

  // 16:9 Canvas Dimensions (1200 x 675)
  const W = 1200;
  const H = 675;

  // Generate SVG Graphic
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" width="${W}" height="${H}">
  <defs>
    <!-- Background Gradient -->
    <linearGradient id="bgGrad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#070c18" />
      <stop offset="50%" stop-color="#0b1326" />
      <stop offset="100%" stop-color="#040711" />
    </linearGradient>

    <!-- Radial Accent Glows -->
    <radialGradient id="blueGlow" cx="0.25" cy="0.3" r="0.7">
      <stop offset="0%" stop-color="#2563eb" stop-opacity="0.32" />
      <stop offset="60%" stop-color="#1d4ed8" stop-opacity="0.08" />
      <stop offset="100%" stop-color="#000000" stop-opacity="0" />
    </radialGradient>

    <radialGradient id="cyanGlow" cx="0.8" cy="0.7" r="0.5">
      <stop offset="0%" stop-color="#06b6d4" stop-opacity="0.18" />
      <stop offset="70%" stop-color="#0284c7" stop-opacity="0.03" />
      <stop offset="100%" stop-color="#000000" stop-opacity="0" />
    </radialGradient>

    <!-- Card Glass Panel Gradient -->
    <linearGradient id="panelGrad" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#111827" stop-opacity="0.88" />
      <stop offset="100%" stop-color="#070c18" stop-opacity="0.94" />
    </linearGradient>

    <!-- Reticle Glow -->
    <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
      <feGaussianBlur stdDeviation="4" result="blur" />
      <feComposite in="SourceGraphic" in2="blur" operator="over" />
    </filter>

    <!-- Cartographic Grid Pattern -->
    <pattern id="cartoGrid" width="60" height="60" patternUnits="userSpaceOnUse">
      <path d="M 60 0 L 0 0 0 60" fill="none" stroke="#3b82f6" stroke-width="0.7" stroke-opacity="0.12" />
      <circle cx="60" cy="60" r="1.5" fill="#60a5fa" fill-opacity="0.25" />
    </pattern>

    <pattern id="denseGrid" width="12" height="12" patternUnits="userSpaceOnUse">
      <path d="M 12 0 L 0 0 0 12" fill="none" stroke="#60a5fa" stroke-width="0.5" stroke-opacity="0.04" />
    </pattern>
  </defs>

  <!-- 1. Background Fill & Grids -->
  <rect width="${W}" height="${H}" fill="url(#bgGrad)" />
  <rect width="${W}" height="${H}" fill="url(#denseGrid)" />
  <rect width="${W}" height="${H}" fill="url(#cartoGrid)" />
  <rect width="${W}" height="${H}" fill="url(#blueGlow)" />
  <rect width="${W}" height="${H}" fill="url(#cyanGlow)" />

  <!-- Outer Cartographic Border -->
  <rect x="24" y="24" width="${W - 48}" height="${H - 48}" rx="20" fill="none" stroke="#3b82f6" stroke-width="1.5" stroke-opacity="0.35" />
  <rect x="32" y="32" width="${W - 64}" height="${H - 64}" rx="14" fill="none" stroke="#ffffff" stroke-width="0.7" stroke-opacity="0.08" />

  <!-- 2. Decorative Cartographic Graticule Coordinates in Corners -->
  <g font-family="ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace" font-size="11" fill="#60a5fa" fill-opacity="0.6">
    <text x="44" y="52">MELAROSA GIS • 111°E — 112°E</text>
    <text x="${W - 44}" y="52" text-anchor="end">7°S — 8°S • WGS84</text>
    <text x="44" y="${H - 42}">EPSG:4326 (GEODETIC)</text>
    <text x="${W - 44}" y="${H - 42}" text-anchor="end">KABUPATEN BOJONEGORO, JAWA TIMUR</text>
  </g>

  <!-- 3. Central Reticle / Geographic Target Crosshair (Right Side) -->
  <g transform="translate(860, 335)">
    <!-- Outer Orbit Circles -->
    <circle r="190" fill="none" stroke="#3b82f6" stroke-width="1" stroke-opacity="0.15" stroke-dasharray="6 8" />
    <circle r="140" fill="none" stroke="#60a5fa" stroke-width="1.2" stroke-opacity="0.25" />
    <circle r="90" fill="none" stroke="#93c5fd" stroke-width="1.5" stroke-opacity="0.4" />
    <circle r="36" fill="#1e3a8a" fill-opacity="0.25" stroke="#60a5fa" stroke-width="2" filter="url(#glow)" />
    <circle r="6" fill="#60a5fa" />

    <!-- Crosshair Axes -->
    <line x1="-220" y1="0" x2="220" y2="0" stroke="#60a5fa" stroke-width="1.2" stroke-opacity="0.5" stroke-dasharray="14 10" />
    <line x1="0" y1="-220" x2="0" y2="220" stroke="#60a5fa" stroke-width="1.2" stroke-opacity="0.5" stroke-dasharray="14 10" />

    <!-- Degree Ticks -->
    <line x1="-90" y1="-10" x2="-90" y2="10" stroke="#60a5fa" stroke-width="1.5" stroke-opacity="0.6" />
    <line x1="90" y1="-10" x2="90" y2="10" stroke="#60a5fa" stroke-width="1.5" stroke-opacity="0.6" />
    <line x1="-10" y1="-90" x2="10" y2="-90" stroke="#60a5fa" stroke-width="1.5" stroke-opacity="0.6" />
    <line x1="-10" y1="90" x2="10" y2="90" stroke="#60a5fa" stroke-width="1.5" stroke-opacity="0.6" />

    <!-- Coordinate Tag Overlay -->
    <rect x="-110" y="52" width="220" height="30" rx="8" fill="#030712" fill-opacity="0.85" stroke="#3b82f6" stroke-width="1" stroke-opacity="0.5" />
    <text x="0" y="72" text-anchor="middle" font-family="ui-monospace, monospace" font-size="11" font-weight="bold" fill="#93c5fd">
      ${formattedLat}, ${formattedLng}
    </text>
  </g>

  <!-- 4. Left Hero Content Card (Glassmorphism Box) -->
  <g transform="translate(64, 90)">
    <!-- Main Card Body -->
    <rect width="560" height="495" rx="24" fill="url(#panelGrad)" stroke="#3b82f6" stroke-width="1.5" stroke-opacity="0.4" />
    <rect width="560" height="495" rx="24" fill="none" stroke="#ffffff" stroke-width="0.8" stroke-opacity="0.12" />

    <!-- Decorative Top Edge Line -->
    <line x1="40" y1="0" x2="240" y2="0" stroke="#60a5fa" stroke-width="3" stroke-linecap="round" />

    <!-- A. Agency Header Badge -->
    <g transform="translate(36, 42)">
      <!-- Bojonegoro Emblem Icon Placeholder Circle -->
      <circle cx="22" cy="22" r="22" fill="#1e3a8a" fill-opacity="0.6" stroke="#3b82f6" stroke-width="1.5" />
      <path d="M 22 10 L 32 17 L 32 28 L 22 34 L 12 28 L 12 17 Z" fill="#60a5fa" fill-opacity="0.85" />
      <circle cx="22" cy="22" r="3" fill="#ffffff" />

      <text x="56" y="18" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="15" font-weight="bold" fill="#ffffff">
        Pemerintah Kabupaten Bojonegoro
      </text>
      <text x="56" y="34" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="12" font-weight="500" fill="#93c5fd">
        Badan Perencanaan Pembangunan Daerah • Bappeda
      </text>
    </g>

    <!-- Dividing Hairline -->
    <line x1="36" y1="104" x2="524" y2="104" stroke="#ffffff" stroke-opacity="0.1" stroke-width="1" />

    <!-- B. Badge: WebGIS 16:9 Interactive Map Preview -->
    <g transform="translate(36, 126)">
      <rect width="180" height="28" rx="14" fill="#1e3a8a" fill-opacity="0.4" stroke="#3b82f6" stroke-width="1" stroke-opacity="0.5" />
      <circle cx="14" cy="14" r="4.5" fill="#10b981" />
      <text x="28" y="19" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="11" font-weight="bold" fill="#93c5fd" letter-spacing="0.5">
        WEBGIS MELAROSA 16:9
      </text>
    </g>

    <!-- C. Map Title & Location Name -->
    <g transform="translate(36, 195)">
      <text font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="34" font-weight="800" fill="#ffffff">
        ${safeTitle}
      </text>
      <text y="38" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="16" font-weight="500" fill="#94a3b8">
        ${safeSubtitle}
      </text>
    </g>

    <!-- D. Metrics / Geographic Spec Grid -->
    <g transform="translate(36, 280)">
      <!-- Item 1: Koordinat WGS84 -->
      <rect width="236" height="64" rx="12" fill="#091122" fill-opacity="0.8" stroke="#3b82f6" stroke-width="1" stroke-opacity="0.25" />
      <text x="14" y="24" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="11" font-weight="bold" fill="#64748b" text-transform="uppercase">
        KOORDINAT PUSAT
      </text>
      <text x="14" y="47" font-family="ui-monospace, monospace" font-size="14" font-weight="bold" fill="#60a5fa">
        ${lat.toFixed(4)}°, ${lng.toFixed(4)}°
      </text>

      <!-- Item 2: Zoom & Skala -->
      <g transform="translate(252, 0)">
        <rect width="236" height="64" rx="12" fill="#091122" fill-opacity="0.8" stroke="#3b82f6" stroke-width="1" stroke-opacity="0.25" />
        <text x="14" y="24" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="11" font-weight="bold" fill="#64748b" text-transform="uppercase">
          TINGKAT ZOOM &amp; BASEMAP
        </text>
        <text x="14" y="47" font-family="ui-monospace, monospace" font-size="14" font-weight="bold" fill="#38bdf8">
          ${zoom}x • ${safeBasemap}
        </text>
      </g>
    </g>

    <!-- E. Footer Branding Bar -->
    <g transform="translate(36, 375)">
      <rect width="488" height="74" rx="14" fill="#030712" fill-opacity="0.6" stroke="#ffffff" stroke-width="1" stroke-opacity="0.08" />
      <text x="20" y="32" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="14" font-weight="bold" fill="#ffffff">
        Portal Geospasial Kabupaten Bojonegoro
      </text>
      <text x="20" y="54" font-family="ui-monospace, monospace" font-size="12" font-weight="600" fill="#60a5fa">
        melarosa.bojonegorokab.go.id/maps
      </text>

      <!-- Compass Rose Indicator (Right side of bar) -->
      <g transform="translate(444, 37)">
        <circle r="18" fill="#1e293b" stroke="#475569" stroke-width="1" />
        <path d="M 0 -13 L 4 0 L 0 3 L -4 0 Z" fill="#ef4444" />
        <path d="M 0 13 L 4 0 L 0 -3 L -4 0 Z" fill="#94a3b8" />
        <text x="0" y="-15" text-anchor="middle" font-family="ui-monospace, monospace" font-size="8" font-weight="bold" fill="#ef4444">N</text>
      </g>
    </g>
  </g>

  <!-- 5. 16:9 Aspect Ratio Tag at Top Right -->
  <g transform="translate(${W - 145}, 65)">
    <rect width="80" height="28" rx="6" fill="#000000" fill-opacity="0.75" stroke="#ffffff" stroke-width="1" stroke-opacity="0.25" />
    <text x="40" y="19" text-anchor="middle" font-family="ui-monospace, monospace" font-size="11" font-weight="bold" fill="#ffffff">
      16:9 HD
    </text>
  </g>
</svg>`;

  return svg;
});
