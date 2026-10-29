// Mirrors GET /api/studio/catalog. Numeric DB decimals may arrive as strings; wrap with Number().

export interface BaseItem {
  id: number;
  name: string;
  slug: string;
  base_price: number;
  piece_count: number;
  labor_minutes: number;
  cure_hours: number;
  max_text_chars: number;
  is_active: boolean;
}

export interface Mold {
  id: number;
  base_item_id: number;
  name: string;
  size_label: string | null;
  depth_mm: number | string;
  price_delta: number;
}

/** Moods store suggestions by NAME, not id. Resolve them against the catalog (see mappers). */
export interface MoodPreset {
  technique?: string | null;
  base_color?: string | null;
  accent_color?: string | null;
  insert?: string | null;
}

export interface Mood {
  id: number;
  name: string;
  preset_json: MoodPreset | null;
  is_active: boolean;
}

export interface ColorSwatch {
  id: number;
  name: string;
  hex: string;
  role: "base" | "accent";
  price_delta: number;
  is_available: boolean;
}

export interface Technique {
  id: number;
  name: string;
  price_delta: number;
  labor_minutes: number;
  requires_accent: boolean;
}

export interface Insert {
  id: number;
  type: string; // flower | foil | shell | glitter
  name: string;
  color: string | null;
  price: number;
  labor_minutes: number;
  min_depth_mm: number | string;
  is_available: boolean;
}

export interface PricedOption {
  id: number;
  name: string;
  price_delta: number;
}

export interface OptionRule {
  id: number;
  subject_type: string;
  subject_id: number;
  rule: "blocks" | "requires";
  target_type: string;
  target_id: number;
  message: string;
}

export interface CheckoutSettings {
  delivery_cities: string[];
  delivery_governorates: string[];
  delivery_fee: number; // piasters
  deposit_percent: number;
  lead_time_days: number;
  claim_window_days: number;
  proof_auto_approve_hours: number;
}

export interface Catalog {
  steps: {
    base_items: BaseItem[];
    molds: Mold[];
    moods: Mood[];
    colors: { base: ColorSwatch[]; accent: ColorSwatch[] };
    techniques: Technique[];
    inserts: Insert[];
    personalization: { fonts: PricedOption[]; text_colors: PricedOption[] };
  };
  rules: OptionRule[];
  expectation_copy_keys: string[];
  checkout: CheckoutSettings;
  catalog_version: string;
}

/** Fast id lookups, built once per catalog load. */
export interface CatalogIndex {
  baseItems: Map<number, BaseItem>;
  molds: Map<number, Mold>;
  moods: Map<number, Mood>;
  baseColors: Map<number, ColorSwatch>;
  accentColors: Map<number, ColorSwatch>;
  techniques: Map<number, Technique>;
  inserts: Map<number, Insert>;
  fonts: Map<number, PricedOption>;
  textColors: Map<number, PricedOption>;
}

function byId<T extends { id: number }>(items: T[]): Map<number, T> {
  return new Map(items.map((item) => [item.id, item]));
}

export function indexCatalog(catalog: Catalog): CatalogIndex {
  const { steps } = catalog;
  return {
    baseItems: byId(steps.base_items),
    molds: byId(steps.molds),
    moods: byId(steps.moods),
    baseColors: byId(steps.colors.base),
    accentColors: byId(steps.colors.accent),
    techniques: byId(steps.techniques),
    inserts: byId(steps.inserts),
    fonts: byId(steps.personalization.fonts),
    textColors: byId(steps.personalization.text_colors),
  };
}
