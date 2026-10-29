import { create } from "zustand";
import { createJSONStorage, persist } from "zustand/middleware";
import { STORAGE_KEYS } from "@/lib/config";
import { FIRST_STEP, LAST_STEP, type StepId } from "../steps";
import type { CatalogIndex } from "../catalog/types";
import {
  EMPTY_SELECTIONS,
  type QuoteSummary,
  type StudioSelections,
  type ValidationResult,
  type ValidationState,
} from "../types";
import { pruneSelections } from "./mappers";
import { persistedSchema, type PersistedStudio } from "./persisted-schema";

const IDLE_VALIDATION: ValidationState = { status: "idle", result: null, error: null };

export interface StudioState {
  // ---- persisted (localStorage: this is the guest draft) ----
  selections: StudioSelections;
  currentStep: StepId;
  /** Furthest step the customer has opened; lets the stepper allow jumping back and forth. */
  highestStep: StepId;
  /** catalog_version the draft was last reconciled against. */
  catalogVersion: string | null;
  savedAt: string | null;
  /** Active quote (30-minute lifetime). Cleared by any selection change. */
  quote: QuoteSummary | null;

  // ---- in-memory only ----
  hydrated: boolean;
  validation: ValidationState;
  /** Labels of options removed because they are no longer in the catalog; null when nothing to say. */
  staleNotice: string[] | null;

  // ---- selection actions (every one clears the quote and stamps savedAt) ----
  setBaseItem: (id: number | null) => void;
  setMold: (id: number | null) => void;
  /** Picks a mood; `preset` only fills fields the customer has not chosen yet. */
  setMood: (id: number | null, preset?: MoodPresetSelections) => void;
  /** Overwrites technique/colors/insert with the mood's suggestions (explicit "use this palette"). */
  applyMoodPreset: (preset: MoodPresetSelections) => void;
  setTechnique: (id: number | null) => void;
  setBaseColor: (id: number | null) => void;
  setAccentColor: (id: number | null) => void;
  toggleInsert: (id: number) => void;
  setFont: (id: number | null) => void;
  setTextColor: (id: number | null) => void;
  setText: (text: string) => void;
  setNote: (note: string) => void;
  addInspirationAssets: (ids: string[]) => void;
  removeInspirationAsset: (id: string) => void;
  /** Replace the whole draft (e.g. reorder's `errors.draft`, already parsed by draftToSelections). */
  loadSelections: (selections: StudioSelections, step?: StepId) => void;
  resetDraft: () => void;

  // ---- navigation ----
  goToStep: (step: StepId) => void;
  nextStep: () => void;
  prevStep: () => void;

  // ---- derived server state ----
  setValidation: (state: ValidationState) => void;
  setValidationResult: (result: ValidationResult) => void;
  setQuote: (quote: QuoteSummary | null) => void;

  // ---- catalog reconciliation ----
  reconcileWithCatalog: (index: CatalogIndex, catalogVersion: string) => void;
  dismissStaleNotice: () => void;
  markHydrated: () => void;
}

export type MoodPresetSelections = Partial<
  Pick<StudioSelections, "techniqueId" | "baseColorId" | "accentColorId" | "insertIds">
>;

const clampStep = (step: number): StepId => Math.min(LAST_STEP, Math.max(FIRST_STEP, step)) as StepId;

export const useStudioStore = create<StudioState>()(
  persist(
    (set, get) => {
      /** Single write path for selection changes: patch, drop the quote, stamp the save time. */
      const patch = (changes: Partial<StudioSelections>) =>
        set((state) => ({
          selections: { ...state.selections, ...changes },
          quote: null,
          savedAt: new Date().toISOString(),
        }));

      return {
        selections: { ...EMPTY_SELECTIONS },
        currentStep: FIRST_STEP,
        highestStep: FIRST_STEP,
        catalogVersion: null,
        savedAt: null,
        quote: null,

        hydrated: false,
        validation: IDLE_VALIDATION,
        staleNotice: null,

        setBaseItem: (id) => {
          if (get().selections.baseItemId === id) return;
          // Molds belong to one item, so a new item invalidates the old mold.
          patch({ baseItemId: id, moldId: null });
        },
        setMold: (id) => patch({ moldId: id }),
        setMood: (id, preset) => {
          const { selections } = get();
          const fill: Partial<StudioSelections> = { moodId: id };
          if (preset) {
            if (selections.techniqueId === null && preset.techniqueId !== undefined) fill.techniqueId = preset.techniqueId;
            if (selections.baseColorId === null && preset.baseColorId !== undefined) fill.baseColorId = preset.baseColorId;
            if (selections.accentColorId === null && preset.accentColorId !== undefined) fill.accentColorId = preset.accentColorId;
            if (selections.insertIds.length === 0 && preset.insertIds !== undefined) fill.insertIds = preset.insertIds;
          }
          patch(fill);
        },
        applyMoodPreset: (preset) => {
          const fill: Partial<StudioSelections> = {};
          if (preset.techniqueId !== undefined) fill.techniqueId = preset.techniqueId;
          if (preset.baseColorId !== undefined) fill.baseColorId = preset.baseColorId;
          if (preset.accentColorId !== undefined) fill.accentColorId = preset.accentColorId;
          if (preset.insertIds !== undefined) fill.insertIds = preset.insertIds;
          patch(fill);
        },
        setTechnique: (id) => patch({ techniqueId: id }),
        setBaseColor: (id) => patch({ baseColorId: id }),
        setAccentColor: (id) => patch({ accentColorId: id }),
        toggleInsert: (id) => {
          const current = get().selections.insertIds;
          patch({ insertIds: current.includes(id) ? current.filter((value) => value !== id) : [...current, id] });
        },
        setFont: (id) => patch({ fontId: id }),
        setTextColor: (id) => patch({ textColorId: id }),
        setText: (text) => patch({ text }),
        setNote: (note) => patch({ note }),
        addInspirationAssets: (ids) => {
          const merged = Array.from(new Set([...get().selections.inspirationAssetIds, ...ids])).slice(0, 3);
          patch({ inspirationAssetIds: merged });
        },
        removeInspirationAsset: (id) =>
          patch({ inspirationAssetIds: get().selections.inspirationAssetIds.filter((value) => value !== id) }),

        loadSelections: (selections, step = FIRST_STEP) =>
          set({
            selections: { ...selections },
            currentStep: step,
            highestStep: step,
            quote: null,
            validation: IDLE_VALIDATION,
            savedAt: new Date().toISOString(),
          }),
        resetDraft: () =>
          set({
            selections: { ...EMPTY_SELECTIONS },
            currentStep: FIRST_STEP,
            highestStep: FIRST_STEP,
            quote: null,
            validation: IDLE_VALIDATION,
            staleNotice: null,
            savedAt: null,
          }),

        goToStep: (step) => set((state) => ({ currentStep: step, highestStep: Math.max(state.highestStep, step) as StepId })),
        nextStep: () => get().goToStep(clampStep(get().currentStep + 1)),
        prevStep: () => set((state) => ({ currentStep: clampStep(state.currentStep - 1) })),

        setValidation: (validation) => set({ validation }),
        setValidationResult: (result) => set({ validation: { status: "ready", result, error: null } }),
        setQuote: (quote) => set({ quote }),

        reconcileWithCatalog: (index, catalogVersion) => {
          const state = get();
          const { selections, removed } = pruneSelections(state.selections, index);
          const changed = removed.length > 0;
          set({
            selections: changed ? selections : state.selections,
            quote: changed ? null : state.quote,
            catalogVersion,
            staleNotice: changed ? Array.from(new Set(removed)) : state.staleNotice,
          });
        },
        dismissStaleNotice: () => set({ staleNotice: null }),
        markHydrated: () => set({ hydrated: true }),
      };
    },
    {
      name: STORAGE_KEYS.studioDraft,
      version: 1,
      storage: createJSONStorage(() => localStorage),
      // Render the server markup first, then call useStudioStore.persist.rehydrate() in an effect.
      // Reading localStorage during the first render would cause a hydration mismatch.
      skipHydration: true,
      partialize: (state): PersistedStudio => ({
        selections: state.selections,
        currentStep: state.currentStep,
        highestStep: state.highestStep,
        catalogVersion: state.catalogVersion,
        savedAt: state.savedAt,
        quote: state.quote,
      }),
      // Never trust storage: parse with zod, keep what is valid, drop an expired quote.
      merge: (persisted, current) => {
        const parsed = persistedSchema.safeParse(persisted);
        if (!parsed.success) return current;
        const { quote, currentStep, highestStep, ...rest } = parsed.data;
        const stillValid = quote !== null && new Date(quote.expires_at).getTime() > Date.now();
        return {
          ...current,
          ...rest,
          currentStep: clampStep(currentStep),
          highestStep: clampStep(Math.max(highestStep, currentStep)),
          quote: stillValid ? quote : null,
        };
      },
      onRehydrateStorage: () => (state) => {
        state?.markHydrated();
      },
    },
  ),
);
