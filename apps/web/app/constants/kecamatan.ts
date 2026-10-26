export interface KecamatanEntry {
  name: string;
  zona: string;
  desc: string;
  coordinate: [number, number];
  zoom: number;
  bbox: [number, number, number, number];
}

export const KECAMATAN_ENTRIES: KecamatanEntry[] = [
  { name: "Balen", zona: "Timur", desc: "Jalur Utama Timur Bojonegoro", coordinate: [111.9667, -7.1667], zoom: 13, bbox: [111.92488, -7.25213, 112.0055, -7.13173] },
  { name: "Baureno", zona: "Timur", desc: "Gerbang Timur Perbatasan Lamongan", coordinate: [112.1000, -7.1333], zoom: 13, bbox: [112.04205, -7.16863, 112.16569, -7.0658] },
  { name: "Bojonegoro", zona: "Kota", desc: "Pusat Pemerintahan & Perkotaan", coordinate: [111.8817, -7.1502], zoom: 14, bbox: [111.85425, -7.18571, 111.9334, -7.1317] },
  { name: "Bubulan", zona: "Selatan", desc: "Dataran Tinggi Selatan", coordinate: [111.8000, -7.3333], zoom: 13, bbox: [111.77185, -7.37793, 111.89488, -7.27264] },
  { name: "Dander", zona: "Tengah", desc: "Kawasan Wisata Kayangan Api", coordinate: [111.8500, -7.2333], zoom: 13, bbox: [111.79122, -7.29911, 111.90272, -7.13922] },
  { name: "Gayam", zona: "Barat", desc: "Pusat Migas Blok Cepu", coordinate: [111.7167, -7.1833], zoom: 13, bbox: [111.66251, -7.21967, 111.74456, -7.12951] },
  { name: "Gondang", zona: "Selatan", desc: "Kawasan Hutan Jati Perbatasan", coordinate: [111.8167, -7.4167], zoom: 13, bbox: [111.75906, -7.46459, 111.91858, -7.34372] },
  { name: "Kalitidu", zona: "Barat", desc: "Kawasan Industri & Pertanian", coordinate: [111.7667, -7.1333], zoom: 13, bbox: [111.7226, -7.1998, 111.84264, -7.10548] },
  { name: "Kanor", zona: "Timur", desc: "Bantaran Bengawan Solo", coordinate: [112.0333, -7.1000], zoom: 13, bbox: [111.98302, -7.16869, 112.08023, -7.066] },
  { name: "Kapas", zona: "Timur", desc: "Kawasan Penyangga Perkotaan", coordinate: [111.9167, -7.1833], zoom: 13, bbox: [111.86835, -7.24025, 111.94449, -7.14215] },
  { name: "Kasiman", zona: "Barat", desc: "Barat Bengawan Solo", coordinate: [111.6000, -7.1000], zoom: 13, bbox: [111.60566, -7.16763, 111.67605, -7.05935] },
  { name: "Kedewan", zona: "Barat", desc: "Geopark Sumur Minyak Tua", coordinate: [111.5833, -7.0333], zoom: 13, bbox: [111.61317, -7.10525, 111.69403, -6.98635] },
  { name: "Kedungadem", zona: "Tenggara", desc: "Perbatasan Wilayah Nganjuk", coordinate: [112.0167, -7.2833], zoom: 13, bbox: [111.97608, -7.40985, 112.09003, -7.23573] },
  { name: "Kepohbaru", zona: "Timur", desc: "Sentra Pertanian Timur", coordinate: [112.0667, -7.2167], zoom: 13, bbox: [112.04966, -7.28384, 112.14345, -7.12866] },
  { name: "Malo", zona: "Barat", desc: "Sentra Kerajinan Gerabah", coordinate: [111.7000, -7.1000], zoom: 13, bbox: [111.6516, -7.16602, 111.78668, -7.06506] },
  { name: "Margomulyo", zona: "Barat", desc: "Kawasan Adat Samin & Ngawi", coordinate: [111.5333, -7.2833], zoom: 13, bbox: [111.42351, -7.37219, 111.55767, -7.248] },
  { name: "Ngambon", zona: "Barat", desc: "Kawasan Hijau Perbukitan", coordinate: [111.7167, -7.2833], zoom: 13, bbox: [111.67133, -7.37216, 111.75986, -7.27843] },
  { name: "Ngasem", zona: "Barat", desc: "Hutan & Geopark Khayangan", coordinate: [111.7667, -7.2333], zoom: 13, bbox: [111.70637, -7.36069, 111.8082, -7.1554] },
  { name: "Ngraho", zona: "Barat", desc: "Jalur Penghubung Ngawi", coordinate: [111.5667, -7.2333], zoom: 13, bbox: [111.46904, -7.30948, 111.60527, -7.20172] },
  { name: "Padangan", zona: "Barat", desc: "Kawasan Perbatasan Cepu", coordinate: [111.6167, -7.1500], zoom: 13, bbox: [111.5639, -7.22502, 111.65792, -7.14472] },
  { name: "Purwosari", zona: "Barat", desc: "Kawasan Energi & Padi", coordinate: [111.6667, -7.1667], zoom: 13, bbox: [111.61431, -7.26926, 111.71596, -7.16435] },
  { name: "Sekar", zona: "Selatan", desc: "Pegunungan Kendeng Selatan", coordinate: [111.7500, -7.4500], zoom: 13, bbox: [111.61378, -7.46964, 111.7992, -7.34509] },
  { name: "Sugihwaras", zona: "Selatan", desc: "Pertanian & Perkebunan", coordinate: [111.9500, -7.3000], zoom: 13, bbox: [111.90994, -7.40743, 112.01031, -7.2607] },
  { name: "Sukosewu", zona: "Timur", desc: "Pertanian Tengah Bojonegoro", coordinate: [111.9333, -7.2333], zoom: 13, bbox: [111.8957, -7.28131, 111.98172, -7.19824] },
  { name: "Sumberrejo", zona: "Timur", desc: "Sentra Niaga Padi Bojonegoro", coordinate: [112.0167, -7.1500], zoom: 13, bbox: [111.98092, -7.26657, 112.07561, -7.15874] },
  { name: "Tambakrejo", zona: "Barat", desc: "Kawasan Hutan Barat Daya", coordinate: [111.6500, -7.2333], zoom: 13, bbox: [111.55112, -7.37086, 111.72619, -7.21512] },
  { name: "Temayang", zona: "Selatan", desc: "Kawasan Wisata Waduk Pacal", coordinate: [111.8833, -7.3167], zoom: 13, bbox: [111.85764, -7.44052, 111.98372, -7.26989] },
  { name: "Trucuk", zona: "Kota", desc: "Utara Jembatan Sosrodilogo", coordinate: [111.8667, -7.1333], zoom: 13, bbox: [111.77488, -7.16522, 111.90167, -7.08193] },
];
