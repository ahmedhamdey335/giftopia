import type { Metadata } from "next";
import { Suspense } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { RegisterForm } from "@/features/auth/register-form";

export const metadata: Metadata = { title: "Create account" };

export default function RegisterPage() {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-3xl">Create your account</CardTitle>
        <CardDescription>Your design draft stays on this device. An account is only needed at checkout.</CardDescription>
      </CardHeader>
      <CardContent>
        <Suspense fallback={<div className="h-96" aria-hidden />}>
          <RegisterForm />
        </Suspense>
      </CardContent>
    </Card>
  );
}
