import type { Metadata } from "next";
import { Suspense } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { LoginForm } from "@/features/auth/login-form";

export const metadata: Metadata = { title: "Sign in" };

export default function LoginPage() {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-3xl">Welcome back</CardTitle>
        <CardDescription>Sign in to check out and follow your orders.</CardDescription>
      </CardHeader>
      <CardContent>
        {/* useSearchParams() inside LoginForm must sit under a Suspense boundary. */}
        <Suspense fallback={<div className="h-64" aria-hidden />}>
          <LoginForm />
        </Suspense>
      </CardContent>
    </Card>
  );
}
