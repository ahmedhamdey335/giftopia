import { API_TIMEOUT_MS, API_URL } from "@/lib/config";
import { ApiError, isAbortError } from "./errors";
import { tokenStorage } from "./token-storage";
import type { Envelope } from "./types";

export type QueryValue = string | number | boolean | null | undefined;

/**
 * required: needs a token (fails fast with 401 if signed out)
 * optional: sends the token when present (default)
 * none:     never sends the token (login, register, public catalog)
 */
export type AuthMode = "required" | "optional" | "none";

export interface ApiRequestOptions {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  query?: Record<string, QueryValue>;
  /** Plain objects are JSON-encoded; FormData is sent as-is (the browser sets the boundary). */
  body?: unknown;
  auth?: AuthMode;
  idempotencyKey?: string;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  timeoutMs?: number;
  keepalive?: boolean;
  cache?: RequestCache;
}

export interface ApiResult<T> {
  data: T;
  message: string;
  status: number;
}

function buildUrl(path: string, query?: Record<string, QueryValue>): string {
  const url = new URL(`${API_URL}${path.startsWith("/") ? path : `/${path}`}`);
  for (const [key, value] of Object.entries(query ?? {})) {
    if (value !== null && value !== undefined) url.searchParams.set(key, String(value));
  }
  return url.toString();
}

function withTimeout(signal: AbortSignal | undefined, timeoutMs: number): AbortSignal {
  const timeout = AbortSignal.timeout(timeoutMs);
  if (!signal) return timeout;
  return typeof AbortSignal.any === "function" ? AbortSignal.any([signal, timeout]) : signal;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

export function newIdempotencyKey(): string {
  return crypto.randomUUID();
}

/** Returns data + message + HTTP status (use when 200 vs 201 matters, e.g. checkout). */
export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<ApiResult<T>> {
  const {
    method = "GET",
    query,
    body,
    auth = "optional",
    idempotencyKey,
    headers: extraHeaders,
    signal,
    timeoutMs = API_TIMEOUT_MS,
    keepalive,
    cache,
  } = options;

  const token = auth === "none" ? null : tokenStorage.get();
  if (auth === "required" && !token) {
    throw new ApiError("Please sign in to continue.", 401);
  }

  const headers = new Headers({ Accept: "application/json", ...extraHeaders });
  if (token) headers.set("Authorization", "Bearer " + token);
  if (idempotencyKey) headers.set("Idempotency-Key", idempotencyKey);

  let payload: BodyInit | undefined;
  if (body instanceof FormData) {
    payload = body;
  } else if (body !== undefined) {
    headers.set("Content-Type", "application/json");
    payload = JSON.stringify(body);
  }

  let response: Response;
  try {
    response = await fetch(buildUrl(path, query), {
      method,
      headers,
      body: payload,
      signal: withTimeout(signal, timeoutMs),
      // Bearer tokens only; never send cookies cross-origin (the API's CORS has credentials off).
      credentials: "omit",
      keepalive,
      // Private/authenticated and mutating calls are never cached. Public GETs revalidate, so the
      // browser sends If-None-Match itself. Do NOT set that header by hand: it is not CORS-safelisted.
      cache: cache ?? (token || method !== "GET" ? "no-store" : "no-cache"),
    });
  } catch (error) {
    if (isAbortError(error)) throw error;
    if (error instanceof DOMException && error.name === "TimeoutError") {
      throw new ApiError("The request took too long. Please try again.", 0);
    }
    throw new ApiError("Cannot reach Giftopia right now. Check your connection and try again.", 0);
  }

  const text = await response.text();
  let envelope: Partial<Envelope<T>> | null = null;
  if (text) {
    try {
      envelope = JSON.parse(text) as Partial<Envelope<T>>;
    } catch {
      /* non-JSON body (gateway/HTML error page) */
    }
  }

  if (!response.ok || envelope?.success === false) {
    if (response.status === 401 && token) {
      // Revoked or expired token: clearing it notifies subscribers, so the session UI signs out.
      tokenStorage.clear();
    }
    throw new ApiError(
      envelope?.message || response.statusText || "Request failed.",
      response.status,
      isRecord(envelope?.errors) ? envelope.errors : {},
    );
  }

  return {
    data: envelope?.data as T,
    message: envelope?.message ?? "OK",
    status: response.status,
  };
}

/** Returns just the unwrapped `data`. */
export async function apiFetch<T>(path: string, options?: ApiRequestOptions): Promise<T> {
  return (await apiRequest<T>(path, options)).data;
}
