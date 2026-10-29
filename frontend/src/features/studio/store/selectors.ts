import type { CatalogIndex } from "../catalog/types";
import type { StepId } from "../steps";
import type { StudioSelections, ValidationResult } from "../types";

export type StepStatus = "complete" | "incomplete";

/** Local (instant) completeness. The server's strict validation stays the final word at quote time. */
export function getStepStatus(step: StepId, s: StudioSelections, index: CatalogIndex | null): StepStatus {
  switch (step) {
    case 1: {
      if (s.baseItemId === null || s.moldId === null) return "incomplete";
      const mold = index?.molds.get(s.moldId);
      return mold && mold.base_item_id !== s.baseItemId ? "incomplete" : "complete";
    }
    case 2:
      return s.moodId !== null && s.techniqueId !== null ? "complete" : "incomplete";
    case 3: {
      if (s.baseColorId === null) return "incomplete";
      const technique = s.techniqueId !== null ? index?.techniques.get(s.techniqueId) : undefined;
      return technique?.requires_accent && s.accentColorId === null ? "incomplete" : "complete";
    }
    case 4:
      return "complete"; // inserts are optional
    case 5: {
      const text = s.text.trim();
      if (text.length === 0) return "complete"; // personalization is optional
      const max = s.baseItemId !== null ? index?.baseItems.get(s.baseItemId)?.max_text_chars : undefined;
      if (max !== undefined && text.length > max) return "incomplete";
      return s.fontId !== null && s.textColorId !== null ? "complete" : "incomplete";
    }
    case 6:
      return [1, 2, 3, 4, 5].every((id) => getStepStatus(id as StepId, s, index) === "complete")
        ? "complete"
        : "incomplete";
  }
}

export function firstIncompleteStep(s: StudioSelections, index: CatalogIndex | null): StepId | null {
  for (const id of [1, 2, 3, 4, 5] as const) {
    if (getStepStatus(id, s, index) === "incomplete") return id;
  }
  return null;
}

/** Options the server says cannot be combined with the current selection (disable them in the UI). */
export function isOptionBlocked(result: ValidationResult | null, type: string, id: number): string | null {
  const hit = result?.blocked_options.find((option) => option.type === type && option.id === id);
  return hit ? hit.message : null;
}
