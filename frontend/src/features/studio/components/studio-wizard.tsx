"use client";

import { Button } from "@/components/ui/button";
import { useCatalogStore } from "../catalog/catalog-store";
import { useFunnelTracking } from "../hooks/use-funnel-tracking";
import { useStudioSession } from "../hooks/use-studio-session";
import { useStudioValidation } from "../hooks/use-studio-validation";
import { getStepStatus } from "../store/selectors";
import { useStudioStore } from "../store/studio-store";
import { selectionsToRequest } from "../store/mappers";
import { STEPS } from "../steps";
import { studioApi } from "../api/studio-api";

export function StudioWizard() {
  const session = useStudioSession();
  useStudioValidation();
  useFunnelTracking();

  const catalog = useCatalogStore((state) => state.catalog);
  const index = useCatalogStore((state) => state.index);
  const selections = useStudioStore((state) => state.selections);
  const currentStep = useStudioStore((state) => state.currentStep);
  const highestStep = useStudioStore((state) => state.highestStep);
  const staleNotice = useStudioStore((state) => state.staleNotice);
  const quote = useStudioStore((state) => state.quote);
  const savedAt = useStudioStore((state) => state.savedAt);
  const setBaseItem = useStudioStore((state) => state.setBaseItem);
  const setMold = useStudioStore((state) => state.setMold);
  const setMood = useStudioStore((state) => state.setMood);
  const setTechnique = useStudioStore((state) => state.setTechnique);
  const setBaseColor = useStudioStore((state) => state.setBaseColor);
  const setAccentColor = useStudioStore((state) => state.setAccentColor);
  const toggleInsert = useStudioStore((state) => state.toggleInsert);
  const goToStep = useStudioStore((state) => state.goToStep);
  const nextStep = useStudioStore((state) => state.nextStep);
  const prevStep = useStudioStore((state) => state.prevStep);
  const setQuote = useStudioStore((state) => state.setQuote);

  if (session.loading) {
    return <div className="mx-auto max-w-5xl px-4 py-12 text-sm text-muted-foreground">Loading the studio…</div>;
  }

  if (session.error || !catalog || !index) {
    return (
      <div className="mx-auto max-w-xl space-y-4 px-4 py-16">
        <p role="alert" className="text-destructive">We could not load the studio: {session.error}</p>
        <Button onClick={() => void session.retry()}>Try again</Button>
      </div>
    );
  }

  const baseItems = catalog.steps.base_items;
  const molds = catalog.steps.molds.filter((mold) => mold.base_item_id === selections.baseItemId);
  const moods = catalog.steps.moods;
  const techniques = catalog.steps.techniques;
  const baseColors = catalog.steps.colors.base;
  const accentColors = catalog.steps.colors.accent;
  const inserts = catalog.steps.inserts;

  const renderStep = () => {
    switch (currentStep) {
      case 1:
        return (
          <div className="space-y-6">
            <div className="space-y-3">
              <h2 className="text-2xl">Base item</h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {baseItems.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => setBaseItem(item.id)}
                    className={
                      "rounded-xl border p-4 text-left transition-colors " +
                      (selections.baseItemId === item.id ? "border-primary bg-secondary" : "border-border bg-card")
                    }
                  >
                    <div className="flex items-center justify-between gap-3">
                      <span className="font-medium">{item.name}</span>
                      <span className="text-sm text-muted-foreground">{item.piece_count} pcs</span>
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">From {item.base_price}</p>
                  </button>
                ))}
              </div>
            </div>
            <div className="space-y-3">
              <h3 className="text-xl">Mold</h3>
              <div className="flex flex-wrap gap-2">
                {molds.map((mold) => (
                  <Button
                    key={mold.id}
                    variant={selections.moldId === mold.id ? "default" : "outline"}
                    onClick={() => setMold(mold.id)}
                  >
                    {mold.name}
                  </Button>
                ))}
              </div>
            </div>
          </div>
        );
      case 2:
        return (
          <div className="space-y-6">
            <div className="space-y-3">
              <h2 className="text-2xl">Mood</h2>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {moods.map((mood) => (
                  <button
                    key={mood.id}
                    type="button"
                    onClick={() => setMood(mood.id)}
                    className={
                      "rounded-xl border p-4 text-left transition-colors " +
                      (selections.moodId === mood.id ? "border-primary bg-secondary" : "border-border bg-card")
                    }
                  >
                    <div className="font-medium">{mood.name}</div>
                  </button>
                ))}
              </div>
            </div>
            <div className="space-y-3">
              <h3 className="text-xl">Technique</h3>
              <div className="grid gap-3 sm:grid-cols-2">
                {techniques.map((technique) => (
                  <button
                    key={technique.id}
                    type="button"
                    onClick={() => setTechnique(technique.id)}
                    className={
                      "rounded-xl border p-4 text-left transition-colors " +
                      (selections.techniqueId === technique.id ? "border-primary bg-secondary" : "border-border bg-card")
                    }
                  >
                    <div className="flex items-center justify-between gap-3">
                      <span>{technique.name}</span>
                      <span className="text-sm">{technique.price_delta}</span>
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">
                      {technique.requires_accent ? "Accent color required" : "Accent color optional"}
                    </p>
                  </button>
                ))}
              </div>
            </div>
          </div>
        );
      case 3:
        return (
          <div className="space-y-6">
            <div className="space-y-3">
              <h2 className="text-2xl">Pigments</h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {baseColors.map((color) => (
                  <button
                    key={color.id}
                    type="button"
                    onClick={() => setBaseColor(color.id)}
                    className={
                      "rounded-xl border p-3 text-left transition-colors " +
                      (selections.baseColorId === color.id ? "border-primary bg-secondary" : "border-border bg-card")
                    }
                  >
                    <div className="flex items-center gap-3">
                      <span className="h-6 w-6 rounded-full border border-border" style={{ backgroundColor: color.hex }} />
                      <span>{color.name}</span>
                    </div>
                  </button>
                ))}
              </div>
            </div>
            <div className="space-y-3">
              <h3 className="text-xl">Accent</h3>
              <div className="grid gap-3 sm:grid-cols-2">
                {accentColors.map((color) => (
                  <button
                    key={color.id}
                    type="button"
                    onClick={() => setAccentColor(color.id)}
                    className={
                      "rounded-xl border p-3 text-left transition-colors " +
                      (selections.accentColorId === color.id ? "border-primary bg-secondary" : "border-border bg-card")
                    }
                  >
                    <div className="flex items-center gap-3">
                      <span className="h-6 w-6 rounded-full border border-border" style={{ backgroundColor: color.hex }} />
                      <span>{color.name}</span>
                    </div>
                  </button>
                ))}
              </div>
            </div>
          </div>
        );
      case 4:
        return (
          <div className="space-y-6">
            <h2 className="text-2xl">Inserts</h2>
            <div className="grid gap-3 sm:grid-cols-2">
              {inserts.map((insert) => (
                <button
                  key={insert.id}
                  type="button"
                  onClick={() => toggleInsert(insert.id)}
                  className={
                    "rounded-xl border p-4 text-left transition-colors " +
                    (selections.insertIds.includes(insert.id) ? "border-primary bg-secondary" : "border-border bg-card")
                  }
                >
                  <div className="flex items-center justify-between gap-3">
                    <span>{insert.name}</span>
                    <span className="text-sm">{insert.price}</span>
                  </div>
                  <p className="mt-2 text-sm text-muted-foreground">{insert.type}</p>
                </button>
              ))}
            </div>
          </div>
        );
      case 5:
        return (
          <div className="space-y-6">
            <h2 className="text-2xl">Personalization</h2>
            <div className="space-y-4">
              <label className="block text-sm font-medium">
                Personalization text
                <textarea
                  value={selections.text}
                  onChange={(event) => useStudioStore.getState().setText(event.target.value)}
                  className="mt-2 min-h-32 w-full rounded-md border border-input bg-background px-3 py-2"
                  maxLength={selections.baseItemId ? 120 : 200}
                />
              </label>
              <label className="block text-sm font-medium">
                Note
                <textarea
                  value={selections.note}
                  onChange={(event) => useStudioStore.getState().setNote(event.target.value)}
                  className="mt-2 min-h-20 w-full rounded-md border border-input bg-background px-3 py-2"
                  maxLength={300}
                />
              </label>
            </div>
          </div>
        );
      case 6:
        return (
          <div className="space-y-5">
            <h2 className="text-2xl">Review</h2>
            {quote ? (
              <div className="rounded-xl border border-border p-4">
                <p className="text-sm text-muted-foreground">Quote expires at</p>
                <p className="mt-2 text-2xl font-medium">{quote.expires_at}</p>
                <p className="mt-3 text-sm">Total: {quote.total}</p>
              </div>
            ) : (
              <p className="text-muted-foreground">Your valid quote will appear here.</p>
            )}
            <Button
              onClick={() => {
                void (async () => {
                  const request = selectionsToRequest(selections, index);
                  const result = await studioApi.quote(request);
                  setQuote(result);
                })();
              }}
            >
              Get my quote
            </Button>
          </div>
        );
      default:
        return null;
    }
  };

  return (
    <div className="mx-auto max-w-6xl space-y-8 px-4 py-10">
      <header className="space-y-4">
        <div className="flex flex-wrap items-center gap-2">
          {STEPS.map((step) => {
            const status = getStepStatus(step.id as 1 | 2 | 3 | 4 | 5 | 6, selections, index);
            const active = currentStep === step.id;
            const reachable = step.id <= highestStep;
            return (
              <button
                key={step.id}
                type="button"
                onClick={() => reachable && goToStep(step.id)}
                className={
                  "rounded-full border px-3 py-2 text-sm " +
                  (active ? "border-primary bg-primary text-primary-foreground" : reachable ? "border-border bg-card" : "border-border bg-muted text-muted-foreground")
                }
              >
                {step.id}. {step.title}
              </button>
            );
          })}
        </div>
        <div className="flex items-center justify-between gap-2">
          <div>
            <p className="text-sm text-muted-foreground">Draft saved</p>
            <p className="text-sm">{savedAt ? new Date(savedAt).toLocaleString() : "Not yet saved"}</p>
          </div>
          {staleNotice && staleNotice.length > 0 && <p className="text-sm text-destructive">Some options were removed.</p>}
        </div>
      </header>

      {renderStep()}

      <div className="flex items-center justify-between gap-4">
        <Button variant="outline" onClick={prevStep} disabled={currentStep === 1}>
          Back
        </Button>
        <Button onClick={nextStep} disabled={currentStep === 6}>
          Next
        </Button>
      </div>
    </div>
  );
}
