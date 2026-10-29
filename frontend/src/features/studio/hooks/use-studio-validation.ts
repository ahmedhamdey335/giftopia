"use client";

import { useEffect, useMemo, useState } from "react";
import { errorMessage, isAbortError, isApiError } from "@/lib/api/errors";
import { studioApi } from "../api/studio-api";
import { useCatalogStore } from "../catalog/catalog-store";
import { isRequestEmpty, selectionsToRequest } from "../store/mappers";
import { useStudioStore } from "../store/studio-store";

const DEBOUNCE_MS = 500;
const RATE_LIMIT_RETRY_MS = 6_000;

/**
 * Debounced live validation + partial pricing. Mount once in the wizard shell.
 *
 * - Waits 500 ms after the last change (the API throttles /studio/* at 30 requests/minute).
 * - Aborts the in-flight request when the selection changes again, so a slow response can never
 *   overwrite a newer one.
 * - On 429 it shows a calm message and retries on its own.
 */
export function useStudioValidation(): void {
  const hydrated = useStudioStore((state) => state.hydrated);
  const selections = useStudioStore((state) => state.selections);
  const index = useCatalogStore((state) => state.index);
  const [retryTick, setRetryTick] = useState(0);

  // Stable key: only a real change to the request re-triggers validation.
  const request = useMemo(() => selectionsToRequest(selections, index), [selections, index]);
  const requestKey = useMemo(() => JSON.stringify(request), [request]);

  useEffect(() => {
    if (!hydrated || !index) return;

    const { setValidation, setValidationResult } = useStudioStore.getState();
    const controller = new AbortController();

    const timer = setTimeout(async () => {
      if (isRequestEmpty(request)) {
        setValidation({ status: "idle", result: null, error: null });
        return;
      }
      const previous = useStudioStore.getState().validation.result;
      setValidation({ status: "validating", result: previous, error: null });
      try {
        setValidationResult(await studioApi.validate(request, controller.signal));
      } catch (error) {
        if (isAbortError(error)) return;
        if (isApiError(error) && error.status === 429) {
          setValidation({ status: "error", result: previous, error: "One moment, we are updating your price…" });
          setTimeout(() => setRetryTick((tick) => tick + 1), RATE_LIMIT_RETRY_MS);
          return;
        }
        setValidation({ status: "error", result: previous, error: errorMessage(error) });
      }
    }, DEBOUNCE_MS);

    return () => {
      clearTimeout(timer);
      controller.abort();
    };
    // `request` is captured through requestKey on purpose.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [hydrated, index, requestKey, retryTick]);
}
