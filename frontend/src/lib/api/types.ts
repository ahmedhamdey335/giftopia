/** Every Laravel response uses this envelope (see docs/api.md). */
export interface Envelope<T> {
  success: boolean;
  message: string;
  data: T;
  errors: Record<string, unknown>;
}

export type UserRole = "customer" | "producer" | "admin";

export interface ApiUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
}

export interface AuthPayload {
  user: ApiUser;
  token: string;
}
