/**
 * Only allow same-site relative redirects (prevents open redirects via ?next=https://evil.example).
 */
export function safeNextPath(value: string | null | undefined, fallback = "/"): string {
  if (!value || !value.startsWith("/") || value.startsWith("//") || value.includes("\\")) {
    return fallback;
  }
  return value;
}
