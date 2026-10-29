import { z } from "zod";
import type { CatalogIndex, Catalog, Mood } from "../catalog/types";
import {
  EMPTY_SELECTIONS,
  type DraftV1,
  type StudioRequest,
  type StudioSelections,
} from "../types";

// ---------- selections -> API request ----------

/**
 * Builds the flat request for /studio/validate and /studio/quote.
 *
 * The store keeps exactly what the customer picked; this function normalises it, so switching a
 * technique back and forth never loses a choice:
 *  - the accent color is only sent when the chosen technique needs one (Solid has no accent),
 *  - font and text color are only sent when there is text to print.
 * Prices are never sent. The server computes everything from IDs.
 */
export function selectionsToRequest(selections: StudioSelections, index?: CatalogIndex | null): StudioRequest {
  const technique = index && selections.techniqueId ? index.techniques.get(selections.techniqueId) : undefined;
  const sendAccent = technique ? technique.requires_accent : true;
  const text = selections.text.trim();
  const note = selections.note.trim();
  const hasText = text.length > 0;

  const request: StudioRequest = { insert_ids: [...selections.insertIds].sort((a, b) => a - b) };
  if (selections.baseItemId !== null) request.base_item_id = selections.baseItemId;
  if (selections.moldId !== null) request.mold_id = selections.moldId;
  if (selections.moodId !== null) request.mood_id = selections.moodId;
  if (selections.techniqueId !== null) request.technique_id = selections.techniqueId;
  if (selections.baseColorId !== null) request.base_color_id = selections.baseColorId;
  if (sendAccent && selections.accentColorId !== null) request.accent_color_id = selections.accentColorId;
  if (hasText) {
    request.personalization_text = text;
    if (selections.fontId !== null) request.font_id = selections.fontId;
    if (selections.textColorId !== null) request.text_color_id = selections.textColorId;
  }
  if (note) request.personalization_note = note;
  if (selections.inspirationAssetIds.length > 0) {
    request.inspiration_asset_ids = [...selections.inspirationAssetIds];
  }
  return request;
}

/** Nothing worth validating yet (avoids a pointless call on an untouched draft). */
export function isRequestEmpty(request: StudioRequest): boolean {
  return (
    request.base_item_id === undefined &&
    request.mold_id === undefined &&
    request.mood_id === undefined &&
    request.technique_id === undefined &&
    request.base_color_id === undefined &&
    request.insert_ids.length === 0
  );
}

// ---------- DraftV1 <-> selections ----------

const nullableId = z.number().int().positive().nullable().default(null);

const draftSchema = z.object({
  schema_version: z.literal(1),
  option_ids: z
    .object({
      base_item_id: nullableId,
      mold_id: nullableId,
      mood_id: nullableId,
      technique_id: nullableId,
      base_color_id: nullableId,
      accent_color_id: nullableId,
      insert_ids: z.array(z.number().int().positive()).default([]),
      font_id: nullableId,
      text_color_id: nullableId,
    })
    .prefault({}),
  text: z.string().default(""),
  note: z.string().default(""),
  inspiration_asset_ids: z.array(z.string()).max(3).default([]),
  saved_at: z.string().optional(),
  catalog_version: z.string().nullable().default(null),
});

export function selectionsToDraft(selections: StudioSelections, catalogVersion: string | null): DraftV1 {
  return {
    schema_version: 1,
    option_ids: {
      base_item_id: selections.baseItemId,
      mold_id: selections.moldId,
      mood_id: selections.moodId,
      technique_id: selections.techniqueId,
      base_color_id: selections.baseColorId,
      accent_color_id: selections.accentColorId,
      insert_ids: [...selections.insertIds],
      font_id: selections.fontId,
      text_color_id: selections.textColorId,
    },
    text: selections.text,
    note: selections.note,
    inspiration_asset_ids: [...selections.inspirationAssetIds],
    saved_at: new Date().toISOString(),
    catalog_version: catalogVersion,
  };
}

/**
 * Parses a draft from an untrusted source (reorder's `errors.draft`, an old localStorage blob).
 * Returns null when it is not a valid schema_version 1 draft.
 */
export function draftToSelections(input: unknown): { selections: StudioSelections; catalogVersion: string | null } | null {
  const parsed = draftSchema.safeParse(input);
  if (!parsed.success) return null;
  const draft = parsed.data;
  return {
    catalogVersion: draft.catalog_version,
    selections: {
      baseItemId: draft.option_ids.base_item_id,
      moldId: draft.option_ids.mold_id,
      moodId: draft.option_ids.mood_id,
      techniqueId: draft.option_ids.technique_id,
      baseColorId: draft.option_ids.base_color_id,
      accentColorId: draft.option_ids.accent_color_id,
      insertIds: draft.option_ids.insert_ids,
      fontId: draft.option_ids.font_id,
      textColorId: draft.option_ids.text_color_id,
      text: draft.text,
      note: draft.note,
      inspirationAssetIds: draft.inspiration_asset_ids,
    },
  };
}

// ---------- stale-draft reconciliation ----------

/**
 * The catalog only lists active/available options, so any id missing from it can no longer be
 * ordered. Drops those ids and reports what was removed, so the UI can ask the customer to repair
 * the selection ("Dried Lavender is no longer available").
 */
export function pruneSelections(
  selections: StudioSelections,
  index: CatalogIndex,
): { selections: StudioSelections; removed: string[] } {
  const removed: string[] = [];
  const next: StudioSelections = { ...selections };

  const keep = (id: number | null, exists: boolean, label: string): number | null => {
    if (id !== null && !exists) {
      removed.push(label);
      return null;
    }
    return id;
  };

  next.baseItemId = keep(selections.baseItemId, selections.baseItemId !== null && index.baseItems.has(selections.baseItemId), "base item");
  next.moldId = keep(selections.moldId, selections.moldId !== null && index.molds.has(selections.moldId), "mold");
  next.moodId = keep(selections.moodId, selections.moodId !== null && index.moods.has(selections.moodId), "mood");
  next.techniqueId = keep(selections.techniqueId, selections.techniqueId !== null && index.techniques.has(selections.techniqueId), "technique");
  next.baseColorId = keep(selections.baseColorId, selections.baseColorId !== null && index.baseColors.has(selections.baseColorId), "base color");
  next.accentColorId = keep(selections.accentColorId, selections.accentColorId !== null && index.accentColors.has(selections.accentColorId), "accent color");
  next.fontId = keep(selections.fontId, selections.fontId !== null && index.fonts.has(selections.fontId), "font");
  next.textColorId = keep(selections.textColorId, selections.textColorId !== null && index.textColors.has(selections.textColorId), "text color");

  next.insertIds = selections.insertIds.filter((id) => {
    const exists = index.inserts.has(id);
    if (!exists) removed.push("insert");
    return exists;
  });

  // A mold that belongs to another item is as stale as a missing one.
  if (next.moldId !== null && next.baseItemId !== null) {
    const mold = index.molds.get(next.moldId);
    if (mold && mold.base_item_id !== next.baseItemId) {
      removed.push("mold");
      next.moldId = null;
    }
  }

  return { selections: next, removed };
}

// ---------- mood presets ----------

/** Moods suggest options by name. Unmatched names are ignored (an admin may have renamed them). */
export function resolveMoodPreset(
  catalog: Catalog,
  mood: Mood,
): Partial<Pick<StudioSelections, "techniqueId" | "baseColorId" | "accentColorId" | "insertIds">> {
  const preset = mood.preset_json;
  if (!preset) return {};
  const same = (a: string, b: string) => a.trim().toLowerCase() === b.trim().toLowerCase();
  const result: ReturnType<typeof resolveMoodPreset> = {};

  if (preset.technique) {
    const match = catalog.steps.techniques.find((item) => same(item.name, preset.technique!));
    if (match) result.techniqueId = match.id;
  }
  if (preset.base_color) {
    const match = catalog.steps.colors.base.find((item) => same(item.name, preset.base_color!));
    if (match) result.baseColorId = match.id;
  }
  if (preset.accent_color) {
    const match = catalog.steps.colors.accent.find((item) => same(item.name, preset.accent_color!));
    if (match) result.accentColorId = match.id;
  }
  if (preset.insert) {
    const match = catalog.steps.inserts.find((item) => same(item.name, preset.insert!));
    if (match) result.insertIds = [match.id];
  }
  return result;
}

export { EMPTY_SELECTIONS };
