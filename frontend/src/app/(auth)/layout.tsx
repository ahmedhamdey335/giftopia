import type { ReactNode } from "react";

export default function AuthLayout({ children }: { children: ReactNode }) {
  return <div className="mx-auto flex w-full max-w-md flex-col px-4 py-12 sm:py-20">{children}</div>;
}
