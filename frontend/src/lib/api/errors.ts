export class ApiError extends Error {
  readonly status: number;
  readonly errors: Record<string, unknown>;

  constructor(message: string, status: number, errors: Record<string, unknown> = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }

  /** Laravel validation errors: { field: ["message", ...] } */
  get fieldErrors(): Record<string, string[]> {
    const result: Record<string, string[]> = {};
    for (const [field, value] of Object.entries(this.errors)) {
      if (Array.isArray(value) && value.every((item) => typeof item === "string")) {
        result[field] = value as string[];
      }
    }
    return result;
  }

  get isNetworkError(): boolean {
    return this.status === 0;
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

export function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === "AbortError";
}

export function errorMessage(error: unknown, fallback = "Something went wrong. Please try again."): string {
  return error instanceof Error && error.message ? error.message : fallback;
}
