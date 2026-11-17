export type LngLat = [number, number];

export type IsoBuildingKind =
  | "civic"
  | "commercial"
  | "religious"
  | "education"
  | "health"
  | "industrial"
  | "heritage"
  | "residential";

export type IsoRoofStyle = "flat" | "gable" | "hip" | "dome" | "stepped";

export interface IsoBuilding {
  id: string;
  name: string;
  kind: IsoBuildingKind;
  roofStyle: IsoRoofStyle;
  ring: LngLat[];
  levels: number;
  heightMeters: number;
  address?: string;
  kelurahan?: string;
  landmarkId?: string;
  source: "osm" | "curated";
}

export type IsoRoadKind =
  | "primary"
  | "secondary"
  | "tertiary"
  | "residential"
  | "bridge"
  | "rail";

export interface IsoRoad {
  id: string;
  name: string;
  kind: IsoRoadKind;
  coords: LngLat[];
  widthMeters: number;
  oneway?: boolean;
  source: "osm" | "curated";
}

export interface IsoWaterArea {
  id: string;
  name: string;
  geometryType: "polygon" | "line";
  coords: LngLat[];
  widthMeters: number;
}

export interface IsoGreenZone {
  id: string;
  name: string;
  kind: "alun_alun" | "park" | "forest" | "field";
  ring: LngLat[];
  treeDensity: number;
}

export interface IsoBoundary {
  id: string;
  name: string;
  ring: LngLat[];
}

export interface IsoLandmark {
  id: string;
  name: string;
  category: string;
  description: string;
  coordinate: LngLat;
  zoom?: number;
  kelurahan?: string;
}

export interface BojonegoroIsometricDataset {
  ok: boolean;
  source: "overpass" | "curated_fallback" | "hybrid";
  fetchedAt: string;
  center: LngLat;
  bbox: [number, number, number, number];
  boundary: IsoBoundary;
  kelurahanBoundaries: IsoBoundary[];
  waterways: IsoWaterArea[];
  greenZones: IsoGreenZone[];
  roads: IsoRoad[];
  buildings: IsoBuilding[];
  landmarks: IsoLandmark[];
  stats: {
    buildings: number;
    roads: number;
    waterways: number;
    greenZones: number;
    landmarks: number;
    kelurahan: number;
  };
}

export interface IsoInspectedFeature {
  id: string;
  kind: "building" | "road" | "water" | "park" | "landmark";
  title: string;
  subtitle: string;
  categoryLabel: string;
  coordinate: LngLat;
  sourceLabel: string;
  attributes: Array<{ label: string; value: string }>;
}

export type IsoThemeMode = "day" | "night";

export type IsoBasemapStyle = "satellite" | "aerial" | "osm" | "pixel";

export type IsoBuildingRenderStyle = "solid" | "translucent" | "footprint";

export type IsoRotation = 0 | 1 | 2 | 3;
