"use client";

import { useEffect } from "react";
import { useCatalogStore } from "../catalog/catalog-store";
import { useStudioStore } from "../store/studio-store";

/**
 * Mount once on /studio. It
 *  1. rehydrates the draft from localStorage after the first paint (no hydration mismatch),
 *  2. loads the catalog,
 *  3. reconciles the draft against it (drops options that are no longer offered).
 * Returns whether the studio is ready to render real content.
 */
export function useStudioSession() {
  const hydrated = useStudioStore((state) => state.hydrated);
  const reconcile = useStudioStore((state) => state.reconcileWithCatalog);
  const catalog = useCatalogStore((state) => state.catalog);
  const index = useCatalogStore((state) => state.index);
  const status = useCatalogStore((state) => state.status);
  const error = useCatalogStore((state) => state.error);
  const load = useCatalogStore((state) => state.load);

  useEffect(() => {
    // `persist` is missing when localStorage is blocked (some private modes). Studio still works,
    // the draft just will not survive a reload.
    if (useStudioStore.persist) {
      void useStudioStore.persist.rehydrate();
    } else {
      useStudioStore.getState().markHydrated();
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const catalogVersion = catalog?.catalog_version ?? null;
  useEffect(() => {
    if (hydrated && index && catalogVersion) reconcile(index, catalogVersion);
  }, [hydrated, index, catalogVersion, reconcile]);

  return {
    ready: hydrated && status === "ready",
    loading: !hydrated || status === "loading" || status === "idle",
    error: status === "error" ? error : null,
    retry: () => load({ force: true }),
  };
}
