import { apiFetch } from "@/lib/api/client";
import type { ApiUser, AuthPayload } from "@/lib/api/types";

export interface LoginInput {
  email: string;
  password: string;
}

export interface RegisterInput extends LoginInput {
  name: string;
  password_confirmation: string;
}

export const authApi = {
  login: (input: LoginInput) =>
    apiFetch<AuthPayload>("/login", { method: "POST", body: input, auth: "none" }),
  register: (input: RegisterInput) =>
    apiFetch<AuthPayload>("/register", { method: "POST", body: input, auth: "none" }),
  me: (signal?: AbortSignal) => apiFetch<ApiUser>("/me", { auth: "required", signal }),
  logout: () => apiFetch<null>("/logout", { method: "POST", auth: "required" }),
};
