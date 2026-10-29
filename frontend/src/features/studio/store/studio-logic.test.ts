import { beforeEach, describe, expect, it } from "vitest";
import { indexCatalog, type Catalog } from "../catalog/types";
import { draftToSelections, pruneSelections, resolveMoodPreset, selectionsToDraft, selectionsToRequest } from "./mappers";
import { firstIncompleteStep, getStepStatus, isOptionBlocked } from "./selectors";
import { EMPTY_SELECTIONS } from "../types";
import { useStudioStore } from "./studio-store";

const catalog: Catalog = {
  catalog_version: "test-v1",
  steps: {
    base_items: [{
      id: 1, name: "Tray", slug: "tray", base_price: 1000, piece_count: 1,
      labor_minutes: 30, cure_hours: 24, max_text_chars: 20, is_active: true,
    }],
    molds: [{ id: 2, base_item_id: 1, name: "Oval", size_label: "20 cm", depth_mm: 6, price_delta: 100 }],
    moods: [{ id: 3, name: "Summer", preset_json: {
      technique: "Gradient", base_color: "Blue", accent_color: "Gold", insert: "Shell",
    }, is_active: true }],
    colors: {
      base: [{ id: 4, name: "Blue", hex: "#0000ff", role: "base", price_delta: 0, is_available: true }],
      accent: [{ id: 5, name: "Gold", hex: "#ffd700", role: "accent", price_delta: 50, is_available: true }],
    },
    techniques: [{ id: 6, name: "Gradient", price_delta: 25, labor_minutes: 10, requires_accent: true }],
    inserts: [{
      id: 7, type: "shell", name: "Shell", color: "white", price: 30, labor_minutes: 5,
      min_depth_mm: 3, is_available: true,
    }],
    personalization: {
      fonts: [{ id: 8, name: "Serif", price_delta: 5 }],
      text_colors: [{ id: 9, name: "Ink", price_delta: 0 }],
    },
  },
  rules: [],
  expectation_copy_keys: [],
  checkout: {
    delivery_cities: ["Tanta"], delivery_governorates: ["Gharbia"], delivery_fee: 5000,
    deposit_percent: 50, lead_time_days: 7, claim_window_days: 3, proof_auto_approve_hours: 48,
  },
};

const index = indexCatalog(catalog);

describe("studio draft and request mapping", () => {
  it("normalizes text, sorts inserts, and omits irrelevant choices", () => {
    const request = selectionsToRequest({
      ...EMPTY_SELECTIONS,
      techniqueId: 6,
      baseColorId: 4,
      accentColorId: 5,
      insertIds: [9, 7],
      fontId: 8,
      textColorId: 9,
      text: "  Sara  ",
      note: "  Handle gently  ",
      inspirationAssetIds: ["asset-1"],
    }, index);

    expect(request).toEqual({
      technique_id: 6,
      base_color_id: 4,
      accent_color_id: 5,
      insert_ids: [7, 9],
      personalization_text: "Sara",
      personalization_note: "Handle gently",
      font_id: 8,
      text_color_id: 9,
      inspiration_asset_ids: ["asset-1"],
    });
  });

  it("omits accents for no-accent techniques and font choices without text", () => {
    const noAccentIndex = indexCatalog({
      ...catalog,
      steps: {
        ...catalog.steps,
        techniques: [{ ...catalog.steps.techniques[0], requires_accent: false }],
      },
    });
    const request = selectionsToRequest({
      ...EMPTY_SELECTIONS, techniqueId: 6, accentColorId: 5, fontId: 8, textColorId: 9,
    }, noAccentIndex);

    expect(request).not.toHaveProperty("accent_color_id");
    expect(request).not.toHaveProperty("font_id");
    expect(request).not.toHaveProperty("text_color_id");
  });

  it("round-trips schema version 1 drafts and rejects invalid versions", () => {
    const selections = { ...EMPTY_SELECTIONS, baseItemId: 1, inspirationAssetIds: ["asset-1"] };
    const draft = selectionsToDraft(selections, catalog.catalog_version);
    expect(draftToSelections(draft)).toEqual({ selections, catalogVersion: "test-v1" });
    expect(draftToSelections({ ...draft, schema_version: 99 })).toBeNull();
  });

  it("removes missing choices and molds that belong to another item", () => {
    const result = pruneSelections({
      ...EMPTY_SELECTIONS, baseItemId: 1, moldId: 99, moodId: 99, insertIds: [7, 99],
    }, index);

    expect(result.selections).toMatchObject({ baseItemId: 1, moldId: null, moodId: null, insertIds: [7] });
    expect(result.removed).toEqual(["mold", "mood", "insert"]);
  });

  it("resolves mood presets by case-insensitive catalog names", () => {
    expect(resolveMoodPreset(catalog, catalog.steps.moods[0])).toEqual({
      techniqueId: 6, baseColorId: 4, accentColorId: 5, insertIds: [7],
    });
  });
});

describe("studio step gating and blocked options", () => {
  it("requires the base item and matching mold before continuing", () => {
    expect(getStepStatus(1, EMPTY_SELECTIONS, index)).toBe("incomplete");
    expect(getStepStatus(1, { ...EMPTY_SELECTIONS, baseItemId: 1, moldId: 2 }, index)).toBe("complete");
    expect(getStepStatus(1, { ...EMPTY_SELECTIONS, baseItemId: 1, moldId: 99 }, index)).toBe("incomplete");
  });

  it("requires an accent only for a technique that needs one and text styling when text is entered", () => {
    const selections = {
      ...EMPTY_SELECTIONS, baseItemId: 1, moldId: 2, moodId: 3, techniqueId: 6, baseColorId: 4,
    };
    expect(getStepStatus(3, selections, index)).toBe("incomplete");
    expect(getStepStatus(3, { ...selections, accentColorId: 5 }, index)).toBe("complete");
    expect(getStepStatus(5, { ...selections, text: "Gift" }, index)).toBe("incomplete");
    expect(getStepStatus(5, { ...selections, text: "Gift", fontId: 8, textColorId: 9 }, index)).toBe("complete");
    expect(firstIncompleteStep(selections, index)).toBe(3);
  });

  it("returns server-provided blocked-option messages", () => {
    const result = {
      allowed: false, blocked: true, violations: [], blocked_options: [
        { type: "insert", id: 7, message: "Too shallow for this mold." },
      ], required_options: [], warnings: [],
      price: { items: [], total: 0, currency: "EGP" },
    };
    expect(isOptionBlocked(result, "insert", 7)).toBe("Too shallow for this mold.");
    expect(isOptionBlocked(result, "font", 8)).toBeNull();
  });
});

describe("studio store mood actions", () => {
  beforeEach(() => {
    localStorage.clear();
    useStudioStore.getState().resetDraft();
  });

  it("fills empty selections from a mood and lets explicit palette use overwrite them", () => {
    const store = useStudioStore.getState();
    store.setTechnique(99);
    store.setMood(3, { techniqueId: 6, baseColorId: 4 });
    expect(useStudioStore.getState().selections).toMatchObject({ moodId: 3, techniqueId: 99, baseColorId: 4 });
    useStudioStore.getState().applyMoodPreset({ techniqueId: 6, baseColorId: 4 });
    expect(useStudioStore.getState().selections).toMatchObject({ techniqueId: 6, baseColorId: 4 });
  });
});
