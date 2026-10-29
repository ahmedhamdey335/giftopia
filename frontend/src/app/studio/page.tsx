import type { Metadata } from "next";
import { StudioFoundationCheck } from "@/features/studio/components/studio-foundation-check";

export const metadata: Metadata = { title: "Studio" };

export default function StudioPage() {
  // Phase 2 step 2 replaces this with <StudioWizard />. For now it proves the foundation works end to end.
  return <StudioFoundationCheck />;
}
