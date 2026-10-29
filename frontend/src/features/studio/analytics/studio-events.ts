import { STORAGE_KEYS } from "@/lib/config";
import { studioApi, type StudioEvent } from "../api/studio-api";
import type { StepId } from "../steps";

/**
 * Funnel analytics (docs/frontend-contract.md): anonymous, no personal data. A random UUID per
 * browser session, a step 1–6 and viewed | completed | abandoned. Batches of up to 20.
 */
const MAX_BATCH = 20;
const FLUSH_DELAY_MS = 4_000;

const queue: StudioEvent[] = [];
let timer: ReturnType<typeof setTimeout> | null = null;

function sessionId(): string {
  try {
    const existing = window.sessionStorage.getItem(STORAGE_KEYS.studioSession);
    if (existing) return existing;
    const created = crypto.randomUUID();
    window.sessionStorage.setItem(STORAGE_KEYS.studioSession, created);
    return created;
  } catch {
    return crypto.randomUUID(); // storage blocked: still unique per call, just not per session
  }
}

export function trackStep(step: StepId, event: StudioEvent["event"]): void {
  if (typeof window === "undefined") return;
  queue.push({ session_id: sessionId(), step, event });
  if (queue.length >= MAX_BATCH) {
    void flushStudioEvents();
  } else if (timer === null) {
    timer = setTimeout(() => void flushStudioEvents(), FLUSH_DELAY_MS);
  }
}

export async function flushStudioEvents(): Promise<void> {
  if (timer !== null) {
    clearTimeout(timer);
    timer = null;
  }
  while (queue.length > 0) {
    const batch = queue.splice(0, MAX_BATCH);
    try {
      await studioApi.sendEvents(batch);
    } catch {
      // Analytics must never break the studio: drop the batch silently.
    }
  }
}
