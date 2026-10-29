import { apiFetch, apiRequest } from "@/lib/api/client";
import type { Catalog } from "../catalog/types";
import type { QuoteSummary, StudioRequest, UploadedAsset, ValidationResult } from "../types";

export const studioApi = {
  /** Public. The browser revalidates via ETag automatically; compare `catalog_version` yourself. */
  catalog: (signal?: AbortSignal) => apiFetch<Catalog>("/studio/catalog", { auth: "none", signal }),

  /** Non-strict: accepts partial configurations, returns warnings/violations and a partial price. */
  validate: (request: StudioRequest, signal?: AbortSignal) =>
    apiFetch<ValidationResult>("/studio/validate", { method: "POST", body: request, auth: "none", signal }),

  /** Strict. 422 => ApiError with `errors.violations`. Sends the token if signed in (links the quote). */
  quote: (request: StudioRequest, signal?: AbortSignal) =>
    apiFetch<QuoteSummary>("/studio/quote", { method: "POST", body: request, signal }),

  /** Up to 3 JPEG/PNG/WebP files, 5 MB each. Allowed for guests. */
  async uploadInspiration(files: File[], signal?: AbortSignal): Promise<UploadedAsset[]> {
    const form = new FormData();
    files.forEach((file) => form.append("files[]", file));
    const { data } = await apiRequest<{ items: UploadedAsset[] }>("/studio/assets", {
      method: "POST",
      body: form,
      signal,
      timeoutMs: 60_000,
    });
    return data.items;
  },

  sendEvents: (events: StudioEvent[]) =>
    apiFetch<unknown>("/studio/events", { method: "POST", body: { events }, auth: "none", keepalive: true }),
};

export interface StudioEvent {
  session_id: string;
  step: number;
  event: "viewed" | "completed" | "abandoned";
}
