import { z } from "zod";

export const loginSchema = z.object({
  email: z.string().trim().min(1, "Enter your email").email("Enter a valid email"),
  password: z.string().min(1, "Enter your password"),
});

export const registerSchema = z
  .object({
    name: z.string().trim().min(1, "Enter your name").max(255),
    email: z.string().trim().min(1, "Enter your email").email("Enter a valid email"),
    password: z.string().min(8, "Use at least 8 characters"),
    password_confirmation: z.string().min(1, "Confirm your password"),
  })
  .refine((value) => value.password === value.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords do not match",
  });

export type LoginValues = z.infer<typeof loginSchema>;
export type RegisterValues = z.infer<typeof registerSchema>;
