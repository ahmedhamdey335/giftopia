"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { safeNextPath } from "@/lib/navigation";
import { useAuth } from "./auth-provider";
import { applyApiErrors } from "./form-errors";
import { registerSchema, type RegisterValues } from "./schemas";

const FIELDS = [
  { name: "name", label: "Full name", type: "text", autoComplete: "name" },
  { name: "email", label: "Email", type: "email", autoComplete: "email" },
  { name: "password", label: "Password", type: "password", autoComplete: "new-password" },
  { name: "password_confirmation", label: "Confirm password", type: "password", autoComplete: "new-password" },
] as const;

export function RegisterForm() {
  const router = useRouter();
  const next = safeNextPath(useSearchParams().get("next"), "/studio");
  const { status, register: registerAccount } = useAuth();
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RegisterValues>({ resolver: zodResolver(registerSchema) });

  useEffect(() => {
    if (status === "authenticated") router.replace(next);
  }, [status, next, router]);

  async function onSubmit(values: RegisterValues) {
    setFormError(null);
    try {
      await registerAccount(values);
      router.replace(next);
    } catch (error) {
      setFormError(applyApiErrors(error, setError));
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
      {formError && (
        <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {formError}
        </p>
      )}

      {FIELDS.map(({ name, label, type, autoComplete }) => (
        <div key={name} className="space-y-2">
          <Label htmlFor={name}>{label}</Label>
          <Input id={name} type={type} autoComplete={autoComplete} aria-invalid={!!errors[name]} {...register(name)} />
          {errors[name] && <p className="text-sm text-destructive">{errors[name]?.message}</p>}
        </div>
      ))}

      <Button type="submit" className="w-full" disabled={isSubmitting}>
        {isSubmitting ? "Creating account…" : "Create account"}
      </Button>

      <p className="text-center text-sm text-muted-foreground">
        Already have an account?{" "}
        <Link href={`/login?next=${encodeURIComponent(next)}`} className="font-medium text-bronze underline-offset-4 hover:underline">
          Sign in
        </Link>
      </p>
    </form>
  );
}
