import type { FieldValues, Path, UseFormSetError } from "react-hook-form";
import { isApiError } from "@/lib/api/errors";

/**
 * Maps a Laravel 422 onto react-hook-form fields. Returns a message for anything that is not a
 * field error (wrong password, rate limit, network), to show in a form-level alert.
 */
export function applyApiErrors<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>): string | null {
  if (!isApiError(error)) return "Something went wrong. Please try again.";

  const fields = error.fieldErrors;
  let mapped = false;
  for (const [field, messages] of Object.entries(fields)) {
    setError(field as Path<T>, { type: "server", message: messages[0] });
    mapped = true;
  }
  if (mapped) return null;

  if (error.status === 429) return "Too many attempts. Please wait a minute and try again.";
  return error.message;
}
