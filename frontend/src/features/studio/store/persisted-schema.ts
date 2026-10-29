import { z } from "zod";
import { EMPTY_SELECTIONS } from "../types";

const id = z.number().int().positive().nullable().catch(null);

const priceLine = z.object({
  key: z.string(),
  id: z.number().nullable().optional(),
  name: z.string(),
  amount: z.number(),
});

const quote = z.object({
  quote_id: z.string(),
  total: z.number(),
  ready_at: z.string(),
  expires_at: z.string(),
  price_breakdown: z.object({ items: z.array(priceLine), total: z.number(), currency: z.string() }),
});

/** What we trust from localStorage. A corrupted or old blob falls back to defaults field by field. */
export const persistedSchema = z.object({
  selections: z
    .object({
      baseItemId: id,
      moldId: id,
      moodId: id,
      techniqueId: id,
      baseColorId: id,
      accentColorId: id,
      insertIds: z.array(z.number().int().positive()).catch([]),
      fontId: id,
      textColorId: id,
      text: z.string().catch(""),
      note: z.string().catch(""),
      inspirationAssetIds: z.array(z.string()).max(3).catch([]),
    })
    .catch({ ...EMPTY_SELECTIONS }),
  currentStep: z.number().int().min(1).max(6).catch(1),
  highestStep: z.number().int().min(1).max(6).catch(1),
  catalogVersion: z.string().nullable().catch(null),
  savedAt: z.string().nullable().catch(null),
  quote: quote.nullable().catch(null),
});

export type PersistedStudio = z.infer<typeof persistedSchema>;
