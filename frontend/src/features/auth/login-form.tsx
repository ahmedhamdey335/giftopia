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
import { loginSchema, type LoginValues } from "./schemas";

export function LoginForm() {
  const router = useRouter();
  const next = safeNextPath(useSearchParams().get("next"), "/studio");
  const { status, login } = useAuth();
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({ resolver: zodResolver(loginSchema) });

  // Already signed in (or just signed in): go where the customer was headed.
  useEffect(() => {
    if (status === "authenticated") router.replace(next);
  }, [status, next, router]);

  async function onSubmit(values: LoginValues) {
    setFormError(null);
    try {
      await login(values);
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

      <div className="space-y-2">
        <Label htmlFor="email">Email</Label>
        <Input id="email" type="email" autoComplete="email" aria-invalid={!!errors.email} {...register("email")} />
        {errors.email && <p className="text-sm text-destructive">{errors.email.message}</p>}
      </div>

      <div className="space-y-2">
        <Label htmlFor="password">Password</Label>
        <Input
          id="password"
          type="password"
          autoComplete="current-password"
          aria-invalid={!!errors.password}
          {...register("password")}
        />
        {errors.password && <p className="text-sm text-destructive">{errors.password.message}</p>}
      </div>

      <Button type="submit" className="w-full" disabled={isSubmitting}>
        {isSubmitting ? "Signing in…" : "Sign in"}
      </Button>

      <p className="text-center text-sm text-muted-foreground">
        New to Giftopia?{" "}
        <Link href={`/register?next=${encodeURIComponent(next)}`} className="font-medium text-bronze underline-offset-4 hover:underline">
          Create an account
        </Link>
      </p>
    </form>
  );
}
