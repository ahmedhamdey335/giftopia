import type { Metadata } from "next";
import { StudioWizard } from "@/features/studio/components/studio-wizard";

export const metadata: Metadata = { title: "Studio" };

export default function StudioPage() {
  return <StudioWizard />;
}
