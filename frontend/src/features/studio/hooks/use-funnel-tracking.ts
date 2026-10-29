"use client";

import { useEffect, useRef } from "react";
import { flushStudioEvents, trackStep } from "../analytics/studio-events";
import { useStudioStore } from "../store/studio-store";

/**
 * Sends `viewed` when a step opens, `completed` when the customer moves forward, and `abandoned`
 * for the open step when they leave the page before getting a quote. Mount once in the wizard shell.
 */
export function useFunnelTracking(): void {
  const currentStep = useStudioStore((state) => state.currentStep);
  const hydrated = useStudioStore((state) => state.hydrated);
  const previous = useRef<number | null>(null);

  useEffect(() => {
    if (!hydrated) return;
    if (previous.current !== null && currentStep > previous.current) {
      trackStep(previous.current as 1 | 2 | 3 | 4 | 5, "completed");
    }
    trackStep(currentStep, "viewed");
    previous.current = currentStep;
  }, [currentStep, hydrated]);

  useEffect(() => {
    // pagehide = the customer is leaving (close, navigate away). Switching tabs is not abandonment.
    const onLeave = () => {
      const { currentStep: step, quote } = useStudioStore.getState();
      if (!quote) trackStep(step, "abandoned");
      void flushStudioEvents();
    };
    window.addEventListener("pagehide", onLeave);
    return () => window.removeEventListener("pagehide", onLeave);
  }, []);
}
