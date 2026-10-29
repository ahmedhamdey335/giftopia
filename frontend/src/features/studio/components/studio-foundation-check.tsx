"use client";

import { Button } from "@/components/ui/button";
import { formatEgp } from "@/lib/money";
import { useFunnelTracking } from "../hooks/use-funnel-tracking";
import { useStudioSession } from "../hooks/use-studio-session";
import { useStudioValidation } from "../hooks/use-studio-validation";
import { useCatalogStore } from "../catalog/catalog-store";
import { STEPS } from "../steps";
import { useStudioStore } from "../store/studio-store";
import { getStepStatus } from "../store/selectors";

/**
 * Temporary page for Phase 2 step 1. It exercises the whole foundation (draft persistence, catalog
 * cache, debounced validation, live price, funnel events) with no wizard UI. Delete it once
 * <StudioWizard /> exists.
 */
export function StudioFoundationCheck() {
  const session = useStudioSession();
  useStudioValidation();
  useFunnelTracking();

  const catalog = useCatalogStore((state) => state.catalog);
  const index = useCatalogStore((state) => state.index);
  const selections = useStudioStore((state) => state.selections);
  const validation = useStudioStore((state) => state.validation);
  const currentStep = useStudioStore((state) => state.currentStep);
  const staleNotice = useStudioStore((state) => state.staleNotice);
  const setBaseItem = useStudioStore((state) => state.setBaseItem);
  const setMold = useStudioStore((state) => state.setMold);
  const resetDraft = useStudioStore((state) => state.resetDraft);
  const nextStep = useStudioStore((state) => state.nextStep);
  const prevStep = useStudioStore((state) => state.prevStep);

  if (session.loading) {
    return <p className="mx-auto max-w-3xl px-4 py-16 text-muted-foreground">Loading the studio…</p>;
  }
  if (session.error || !catalog) {
    return (
      <div className="mx-auto max-w-3xl space-y-4 px-4 py-16">
        <p role="alert">We could not load the studio: {session.error}</p>
        <Button onClick={() => void session.retry()}>Try again</Button>
      </div>
    );
  }

  const molds = catalog.steps.molds.filter((mold) => mold.base_item_id === selections.baseItemId);

  return (
    <div className="mx-auto max-w-3xl space-y-8 px-4 py-12 sm:px-6">
      <header className="space-y-1">
        <h1 className="text-4xl">Studio foundation check</h1>
        <p className="text-sm text-muted-foreground">
          Catalog version {catalog.catalog_version.slice(0, 8)} · step {currentStep} of {STEPS.length} ·{" "}
          {STEPS[currentStep - 1].title}
        </p>
      </header>

      {staleNotice && (
        <p role="status" className="rounded-md border border-border bg-accent px-3 py-2 text-sm">
          Some options are no longer offered and were removed: {staleNotice.join(", ")}.
        </p>
      )}

      <section className="space-y-3">
        <h2 className="text-2xl">1. Base item</h2>
        <div className="flex flex-wrap gap-2">
          {catalog.steps.base_items.map((item) => (
            <Button
              key={item.id}
              variant={selections.baseItemId === item.id ? "default" : "outline"}
              onClick={() => setBaseItem(item.id)}
            >
              {item.name}
            </Button>
          ))}
        </div>
        <div className="flex flex-wrap gap-2">
          {molds.map((mold) => (
            <Button
              key={mold.id}
              size="sm"
              variant={selections.moldId === mold.id ? "default" : "outline"}
              onClick={() => setMold(mold.id)}
            >
              {mold.name}
            </Button>
          ))}
        </div>
      </section>

      <section className="space-y-2 rounded-lg border border-border bg-card p-4">
        <h2 className="text-2xl">Live price</h2>
        <p className="text-sm text-muted-foreground">Validation: {validation.status}</p>
        {validation.error && <p role="alert" className="text-sm text-destructive">{validation.error}</p>}
        {validation.result && (
          <>
            <p className="text-3xl font-display">{formatEgp(validation.result.price.total)}</p>
            <ul className="text-sm">
              {validation.result.price.items.map((line) => (
                <li key={`${line.key}-${line.id ?? "x"}`} className="flex justify-between">
                  <span>{line.name}</span>
                  <span>{formatEgp(line.amount)}</span>
                </li>
              ))}
            </ul>
          </>
        )}
        <p className="text-sm text-muted-foreground">
          Steps complete:{" "}
          {([1, 2, 3, 4, 5, 6] as const).map((id) => (getStepStatus(id, selections, index) === "complete" ? id : "·")).join(" ")}
        </p>
      </section>

      <div className="flex flex-wrap gap-2">
        <Button variant="outline" onClick={prevStep}>Back</Button>
        <Button onClick={nextStep}>Next step</Button>
        <Button variant="ghost" onClick={resetDraft}>Reset draft</Button>
      </div>
    </div>
  );
}
