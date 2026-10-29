"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  useSyncExternalStore,
  type ReactNode,
} from "react";
import { isAbortError, isApiError } from "@/lib/api/errors";
import { tokenStorage } from "@/lib/api/token-storage";
import type { ApiUser } from "@/lib/api/types";
import { authApi, type LoginInput, type RegisterInput } from "./auth-api";

export type AuthStatus = "loading" | "authenticated" | "guest";

interface AuthContextValue {
  status: AuthStatus;
  user: ApiUser | null;
  login: (input: LoginInput) => Promise<ApiUser>;
  register: (input: RegisterInput) => Promise<ApiUser>;
  logout: () => Promise<void>;
  /** Re-fetch the current user (e.g. after a role change). */
  refresh: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

const noopSubscribe = () => () => {};

/** Result of resolving one specific token. `user: null` means we could not load it. */
interface Resolved {
  token: string;
  user: ApiUser | null;
}

/**
 * The token store is the single source of truth for "signed in or not"; the status is derived from
 * it, so there is no state to keep in sync and no setState-in-effect. The only state is the user
 * object fetched for the current token.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  // false on the server and during hydration, true afterwards: first paint is always "loading".
  const mounted = useSyncExternalStore(noopSubscribe, () => true, () => false);
  const token = useSyncExternalStore(tokenStorage.subscribe, tokenStorage.get, () => null);

  const [resolved, setResolved] = useState<Resolved | null>(null);
  const [refreshTick, setRefreshTick] = useState(0);

  useEffect(() => {
    if (!token) return;
    const controller = new AbortController();
    authApi
      .me(controller.signal)
      .then((user) => setResolved({ token, user }))
      .catch((error: unknown) => {
        if (isAbortError(error)) return;
        // 401: the client already cleared the token (store notifies, status flips to guest).
        // Anything else (offline): keep the token so a later refresh can recover; show as guest.
        if (!(isApiError(error) && error.status === 401)) setResolved({ token, user: null });
      });
    return () => controller.abort();
  }, [token, refreshTick]);

  let status: AuthStatus = "loading";
  let user: ApiUser | null = null;
  if (mounted) {
    if (!token) {
      status = "guest";
    } else if (resolved?.token === token) {
      status = resolved.user ? "authenticated" : "guest";
      user = resolved.user;
    }
  }

  const login = useCallback(async (input: LoginInput) => {
    const { user: loggedIn, token: newToken } = await authApi.login(input);
    setResolved({ token: newToken, user: loggedIn }); // before the store notifies: no loading flash
    tokenStorage.set(newToken);
    return loggedIn;
  }, []);

  const register = useCallback(async (input: RegisterInput) => {
    const { user: created, token: newToken } = await authApi.register(input);
    setResolved({ token: newToken, user: created });
    tokenStorage.set(newToken);
    return created;
  }, []);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch {
      /* the token may already be revoked; sign out locally regardless */
    } finally {
      tokenStorage.clear();
      setResolved(null);
    }
  }, []);

  const refresh = useCallback(() => setRefreshTick((tick) => tick + 1), []);

  const value = useMemo<AuthContextValue>(
    () => ({ status, user, login, register, logout, refresh }),
    [status, user, login, register, logout, refresh],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error("useAuth must be used inside <AuthProvider>.");
  return context;
}
