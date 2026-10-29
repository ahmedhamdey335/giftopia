import { STORAGE_KEYS } from "@/lib/config";

const listeners = new Set<() => void>();
const notify = () => listeners.forEach((listener) => listener());

/**
 * Single place that knows where the Sanctum token lives. It is also an external store, so React can
 * subscribe with useSyncExternalStore: sign-in/out in this tab or any other tab updates the UI.
 *
 * Trade-off: localStorage is readable by any script on the page (XSS). We accept it because the
 * API is cross-origin and bearer-token based. Keep third-party scripts out and set a Sanctum token
 * expiry server-side. To move to httpOnly cookies later, only this file and the API client change
 * (via a Next.js route-handler proxy).
 */
export const tokenStorage = {
  get(): string | null {
    if (typeof window === "undefined") return null;
    try {
      return window.localStorage.getItem(STORAGE_KEYS.token);
    } catch {
      return null;
    }
  },
  set(token: string): void {
    try {
      window.localStorage.setItem(STORAGE_KEYS.token, token);
    } catch {
      /* storage blocked (private mode): the session lasts until reload */
    }
    notify();
  },
  clear(): void {
    try {
      window.localStorage.removeItem(STORAGE_KEYS.token);
    } catch {
      /* ignore */
    }
    notify();
  },
  /** Stable reference, as useSyncExternalStore requires. */
  subscribe(listener: () => void): () => void {
    listeners.add(listener);
    const onStorage = (event: StorageEvent) => {
      if (event.key === STORAGE_KEYS.token || event.key === null) listener();
    };
    window.addEventListener("storage", onStorage); // other tabs
    return () => {
      listeners.delete(listener);
      window.removeEventListener("storage", onStorage);
    };
  },
};
