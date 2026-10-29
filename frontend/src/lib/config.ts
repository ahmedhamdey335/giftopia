const DEFAULT_API_URL = "http://localhost:8080/api";

/** Laravel API base URL, without a trailing slash. Set NEXT_PUBLIC_API_URL in .env.local. */
export const API_URL = (process.env.NEXT_PUBLIC_API_URL ?? DEFAULT_API_URL).replace(/\/+$/, "");

export const API_TIMEOUT_MS = 15_000;

export const STORAGE_KEYS = {
  token: "giftopia.token",
  studioDraft: "giftopia.studio.draft",
  studioSession: "giftopia.studio.session",
} as const;
