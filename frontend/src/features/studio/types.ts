import type { StepId } from "./steps";

// ---------- Selections: what the customer has chosen (the draft) ----------

export interface StudioSelections {
  baseItemId: number | null;
  moldId: number | null;
  moodId: number | null;
  techniqueId: number | null;
  baseColorId: number | null;
  accentColorId: number | null;
  insertIds: number[];
  fontId: number | null;
  textColorId: number | null;
  text: string;
  note: string;
  /** UUIDs returned by POST /studio/assets (max 3). */
  inspirationAssetIds: string[];
}

export const EMPTY_SELECTIONS: StudioSelections = {
  baseItemId: null,
  moldId: null,
  moodId: null,
  techniqueId: null,
  baseColorId: null,
  accentColorId: null,
  insertIds: [],
  fontId: null,
  textColorId: null,
  text: "",
  note: "",
  inspirationAssetIds: [],
};

/** Request body for POST /studio/validate and /studio/quote (flat snake_case keys). */
export interface StudioRequest {
  base_item_id?: number;
  mold_id?: number;
  mood_id?: number;
  technique_id?: number;
  base_color_id?: number;
  accent_color_id?: number;
  insert_ids: number[];
  font_id?: number;
  text_color_id?: number;
  personalization_text?: string;
  personalization_note?: string;
  inspiration_asset_ids?: string[];
}

// ---------- Browser draft schema (docs/frontend-contract.md, schema_version 1) ----------

export interface DraftV1 {
  schema_version: 1;
  option_ids: {
    base_item_id: number | null;
    mold_id: number | null;
    mood_id: number | null;
    technique_id: number | null;
    base_color_id: number | null;
    accent_color_id: number | null;
    insert_ids: number[];
    font_id: number | null;
    text_color_id: number | null;
  };
  text: string;
  note: string;
  inspiration_asset_ids: string[];
  saved_at: string;
  catalog_version: string | null;
}

// ---------- API results ----------

export interface PriceLine {
  key: string;
  id?: number | null;
  name: string;
  amount: number; // piasters
}

export interface PriceBreakdown {
  items: PriceLine[];
  total: number; // piasters
  currency: string;
}

export interface RuleIssue {
  rule: string; // "blocks" | "requires" | "invalid"
  message: string;
  subject_type: string;
  subject_id: number | null;
  target_type: string | null;
  target_id: number | null;
}

export interface OptionHint {
  type: string; // morph key: base_item | mold | mood | technique | color_swatch | insert | font | text_color
  id: number;
  message: string;
}

export interface ValidationResult {
  allowed: boolean;
  blocked: boolean;
  violations: RuleIssue[];
  blocked_options: OptionHint[];
  required_options: OptionHint[];
  warnings: RuleIssue[];
  price: PriceBreakdown;
}

export interface QuoteSummary {
  quote_id: string;
  price_breakdown: PriceBreakdown;
  total: number; // piasters
  ready_at: string; // indicative; the promise is lead_time_days after the deposit
  expires_at: string; // ISO; drives the 30-minute countdown
}

export interface UploadedAsset {
  id: string;
  kind: string;
  mime: string;
  size: number;
  file_url: string; // signed, expires in 15 minutes
}

export type ValidationStatus = "idle" | "validating" | "ready" | "error";

export interface ValidationState {
  status: ValidationStatus;
  result: ValidationResult | null;
  error: string | null;
}

export type { StepId };
