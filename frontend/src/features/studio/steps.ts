/** The six wizard steps. `id` doubles as the funnel step number the API expects (1–6). */
export const STEPS = [
  { id: 1, key: "base", title: "Base item", hint: "Choose your piece and its shape" },
  { id: 2, key: "mood", title: "Mood & technique", hint: "Set the feeling and how colors blend" },
  { id: 3, key: "pigments", title: "Pigments", hint: "Pick a base and an accent color" },
  { id: 4, key: "inserts", title: "Inserts", hint: "Flowers, foils, shells and glitter" },
  { id: 5, key: "personalize", title: "Personalize", hint: "Text, font and inspiration photos" },
  { id: 6, key: "review", title: "Review & quote", hint: "Check everything and lock your price" },
] as const;

export type StepId = (typeof STEPS)[number]["id"];

export const FIRST_STEP: StepId = 1;
export const LAST_STEP: StepId = 6;
