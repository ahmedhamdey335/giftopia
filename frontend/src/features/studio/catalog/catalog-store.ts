import { create } from "zustand";
import { errorMessage } from "@/lib/api/errors";
import { studioApi } from "../api/studio-api";
import { indexCatalog, type Catalog, type CatalogIndex } from "./types";

type CatalogStatus = "idle" | "loading" | "ready" | "error";

interface CatalogState {
  status: CatalogStatus;
  catalog: Catalog | null;
  index: CatalogIndex | null;
  error: string | null;
  /** Loads once and de-duplicates concurrent callers. Pass force to refetch (e.g. on window focus). */
  load: (options?: { force?: boolean }) => Promise<void>;
}

let inflight: Promise<void> | null = null;

export const useCatalogStore = create<CatalogState>()((set, get) => ({
  status: "idle",
  catalog: null,
  index: null,
  error: null,

  load: async ({ force = false } = {}) => {
    const { status } = get();
    if (inflight) return inflight;
    if (status === "ready" && !force) return;

    set({ status: get().catalog ? "ready" : "loading", error: null });
    inflight = (async () => {
      try {
        const catalog = await studioApi.catalog();
        set({ status: "ready", catalog, index: indexCatalog(catalog), error: null });
      } catch (error) {
        set({ status: get().catalog ? "ready" : "error", error: errorMessage(error) });
      } finally {
        inflight = null;
      }
    })();
    return inflight;
  },
}));
