"use client";

import { useMemo } from "react";
import { Button } from "@/components/ui/button";
import { formatEgp } from "@/lib/money";
import { useCatalogStore } from "../catalog/catalog-store";
import { useFunnelTracking } from "../hooks/use-funnel-tracking";
import { useStudioSession } from "../hooks/use-studio-session";
import { useStudioValidation } from "../hooks/use-studio-validation";
import { STEPS } from "../steps";
import { resolveMoodPreset } from "../store/mappers";
import { firstIncompleteStep, getStepStatus, isOptionBlocked } from "../store/selectors";
import { useStudioStore } from "../store/studio-store";

function StudioSkeleton() {
  return (
    <div className="mx-auto max-w-6xl space-y-8 px-4 py-10" aria-label="Loading the design studio">
      <div className="h-10 w-full animate-pulse rounded-full bg-muted" />
      <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="space-y-4">
          <div className="h-8 w-48 animate-pulse rounded bg-muted" />
          <div className="grid gap-3 sm:grid-cols-2">
            {[0, 1, 2, 3].map((item) => (
              <div key={item} className="h-28 animate-pulse rounded-xl bg-muted" />
            ))}
          </div>
        </div>
        <div className="hidden h-64 animate-pulse rounded-xl bg-muted lg:block" />
      </div>
      <p className="sr-only" role="status">Loading the studio…</p>
    </div>
  );
}

function SelectionButton({
  selected,
  children,
  onClick,
  disabled,
  describedBy,
}: {
  selected: boolean;
  children: React.ReactNode;
  onClick: () => void;
  disabled?: boolean;
  describedBy?: string;
}) {
  return (
    <button
      type="button"
      aria-pressed={selected}
      aria-describedby={describedBy}
      disabled={disabled}
      onClick={onClick}
      className={`min-h-16 rounded-xl border p-4 text-start transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
        selected ? "border-primary bg-secondary" : "border-border bg-card hover:bg-muted"
      }`}
    >
      <span className="flex items-center justify-between gap-3">
        <span>{children}</span>
        {selected && <span aria-hidden="true" className="text-bronze">✓</span>}
      </span>
    </button>
  );
}

export function StudioWizard() {
  const session = useStudioSession();
  useStudioValidation();
  useFunnelTracking();

  const catalog = useCatalogStore((state) => state.catalog);
  const index = useCatalogStore((state) => state.index);
  const selections = useStudioStore((state) => state.selections);
  const validation = useStudioStore((state) => state.validation);
  const currentStep = useStudioStore((state) => state.currentStep);
  const highestStep = useStudioStore((state) => state.highestStep);
  const savedAt = useStudioStore((state) => state.savedAt);
  const staleNotice = useStudioStore((state) => state.staleNotice);
  const setBaseItem = useStudioStore((state) => state.setBaseItem);
  const setMold = useStudioStore((state) => state.setMold);
  const setMood = useStudioStore((state) => state.setMood);
  const applyMoodPreset = useStudioStore((state) => state.applyMoodPreset);
  const setTechnique = useStudioStore((state) => state.setTechnique);
  const setBaseColor = useStudioStore((state) => state.setBaseColor);
  const setAccentColor = useStudioStore((state) => state.setAccentColor);
  const toggleInsert = useStudioStore((state) => state.toggleInsert);
  const setText = useStudioStore((state) => state.setText);
  const setNote = useStudioStore((state) => state.setNote);
  const dismissStaleNotice = useStudioStore((state) => state.dismissStaleNotice);
  const goToStep = useStudioStore((state) => state.goToStep);
  const nextStep = useStudioStore((state) => state.nextStep);
  const prevStep = useStudioStore((state) => state.prevStep);

  const currentStatus = index ? getStepStatus(currentStep, selections, index) : "incomplete";
  const incompleteStep = useMemo(
    () => (index ? firstIncompleteStep(selections, index) : null),
    [index, selections],
  );
  const price = validation.result?.price ?? null;
  const selectedBaseItem = selections.baseItemId ? index?.baseItems.get(selections.baseItemId) : undefined;
  const availableMolds = catalog?.steps.molds.filter((mold) => mold.base_item_id === selections.baseItemId) ?? [];
  const canGoForward = currentStep < 6 && currentStatus === "complete";

  if (session.loading) return <StudioSkeleton />;

  if (session.error || !catalog || !index) {
    return (
      <div className="mx-auto max-w-xl space-y-4 px-4 py-16">
        <h1 className="text-3xl">Design your keepsake</h1>
        <p role="alert" className="text-destructive">
          We could not load the studio: {session.error ?? "The catalog is unavailable."}
        </p>
        <Button onClick={() => void session.retry()}>Try again</Button>
      </div>
    );
  }

  const renderStep = () => {
    switch (currentStep) {
      case 1:
        return (
          <div className="space-y-6">
            <section className="space-y-3" aria-labelledby="base-item-heading">
              <h2 id="base-item-heading" className="text-2xl">Choose your base item</h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {catalog.steps.base_items.map((item) => (
                  <SelectionButton
                    key={item.id}
                    selected={selections.baseItemId === item.id}
                    onClick={() => setBaseItem(item.id)}
                  >
                    <span className="block font-medium">{item.name}</span>
                    <span className="mt-1 block text-sm text-muted-foreground">
                      From {formatEgp(item.base_price)} · {item.piece_count} pieces
                    </span>
                  </SelectionButton>
                ))}
              </div>
            </section>
            <section className="space-y-3" aria-labelledby="mold-heading">
              <h2 id="mold-heading" className="text-2xl">Choose a mold</h2>
              {selections.baseItemId === null ? (
                <p className="text-sm text-muted-foreground">Choose a base item first to see its molds.</p>
              ) : (
                <div className="grid gap-3 sm:grid-cols-2">
                  {availableMolds.map((mold) => (
                    <SelectionButton
                      key={mold.id}
                      selected={selections.moldId === mold.id}
                      onClick={() => setMold(mold.id)}
                    >
                      <span className="block font-medium">{mold.name}</span>
                      <span className="mt-1 block text-sm text-muted-foreground">
                        {mold.size_label ?? "Standard size"} · Price difference {formatEgp(mold.price_delta)}
                      </span>
                    </SelectionButton>
                  ))}
                </div>
              )}
            </section>
          </div>
        );
      case 2: {
        const selectedMood = selections.moodId === null ? undefined : index.moods.get(selections.moodId);
        return (
          <div className="space-y-6">
            <section className="space-y-3">
              <h2 className="text-2xl">Choose a mood</h2>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {catalog.steps.moods.map((mood) => (
                  <SelectionButton
                    key={mood.id}
                    selected={selections.moodId === mood.id}
                    onClick={() => setMood(mood.id, resolveMoodPreset(catalog, mood))}
                  >
                    {mood.name}
                  </SelectionButton>
                ))}
              </div>
              {selectedMood && (
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => applyMoodPreset(resolveMoodPreset(catalog, selectedMood))}
                >
                  Use this mood&apos;s palette
                </Button>
              )}
            </section>
            <section className="space-y-3">
              <h2 className="text-2xl">Choose a technique</h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {catalog.steps.techniques.map((technique) => (
                  <SelectionButton
                    key={technique.id}
                    selected={selections.techniqueId === technique.id}
                    onClick={() => setTechnique(technique.id)}
                  >
                    <span className="block font-medium">{technique.name}</span>
                    <span className="mt-1 block text-sm text-muted-foreground">
                      Price difference {formatEgp(technique.price_delta)} ·{" "}
                      {technique.requires_accent ? "Accent required" : "No accent required"}
                    </span>
                  </SelectionButton>
                ))}
              </div>
            </section>
          </div>
        );
      }
      case 3: {
        const technique = selections.techniqueId === null ? undefined : index.techniques.get(selections.techniqueId);
        return (
          <div className="space-y-6">
            <h2 className="text-2xl">Choose your pigments</h2>
            {(["base", ...(technique?.requires_accent ? ["accent" as const] : [])] as const).map((role) => {
              const colors = catalog.steps.colors[role];
              const selectedId = role === "base" ? selections.baseColorId : selections.accentColorId;
              const setColor = role === "base" ? setBaseColor : setAccentColor;
              return (
                <section key={role} className="space-y-3">
                  <h3 className="text-xl">{role === "base" ? "Base color" : "Accent color"}</h3>
                  <div className="grid gap-3 sm:grid-cols-2">
                    {colors.map((color) => {
                      const blockedMessage = isOptionBlocked(validation.result, "color_swatch", color.id);
                      const unavailableMessage = color.is_available ? null : `${color.name} is currently unavailable.`;
                      const helperMessage = blockedMessage ?? unavailableMessage;
                      const helperId = `pigment-help-${role}-${color.id}`;
                      return (
                        <div key={color.id}>
                          <SelectionButton
                            selected={selectedId === color.id}
                            disabled={helperMessage !== null && selectedId !== color.id}
                            describedBy={helperMessage ? helperId : undefined}
                            onClick={() => setColor(selectedId === color.id ? null : color.id)}
                          >
                            <span className="flex items-center gap-3">
                              <span
                                aria-hidden="true"
                                className="h-7 w-7 shrink-0 rounded-full border border-border"
                                style={{ backgroundColor: color.hex }}
                              />
                              <span>
                                <span className="block font-medium">{color.name}</span>
                                <span className="block text-sm text-muted-foreground">
                                  {color.price_delta === 0 ? "No added cost" : `Price difference ${formatEgp(color.price_delta)}`}
                                </span>
                              </span>
                            </span>
                          </SelectionButton>
                          {helperMessage && (
                            <p id={helperId} className="mt-1 text-sm text-destructive">{helperMessage}</p>
                          )}
                        </div>
                      );
                    })}
                  </div>
                </section>
              );
            })}
          </div>
        );
      }
      case 4:
        return (
          <section className="space-y-3">
            <h2 className="text-2xl">Choose optional inserts</h2>
            <div className="grid gap-3 sm:grid-cols-2">
              {catalog.steps.inserts.map((insert) => (
                <SelectionButton
                  key={insert.id}
                  selected={selections.insertIds.includes(insert.id)}
                  onClick={() => toggleInsert(insert.id)}
                >
                  {insert.name}
                </SelectionButton>
              ))}
            </div>
          </section>
        );
      case 5:
        return (
          <section className="space-y-4">
            <h2 className="text-2xl">Personalize your piece</h2>
            <label className="block space-y-2 text-sm font-medium" htmlFor="personalization-text">
              <span>Personalization text</span>
              <textarea
                id="personalization-text"
                value={selections.text}
                onChange={(event) => setText(event.target.value)}
                className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2"
                maxLength={selectedBaseItem?.max_text_chars ?? 120}
              />
            </label>
            <label className="block space-y-2 text-sm font-medium" htmlFor="personalization-note">
              <span>Note for the maker</span>
              <textarea
                id="personalization-note"
                value={selections.note}
                onChange={(event) => setNote(event.target.value)}
                className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2"
                maxLength={300}
              />
            </label>
          </section>
        );
      case 6:
        return (
          <section className="space-y-4">
            <h2 className="text-2xl">Review your design</h2>
            <p className="text-muted-foreground">
              Check your selections and live estimate before requesting a quote.
            </p>
            {incompleteStep !== null && (
              <p className="rounded-lg border border-border bg-muted p-3 text-sm">
                Finish “{STEPS[incompleteStep - 1].title}” before requesting a quote.
              </p>
            )}
            {price && (
              <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                {price.items.map((line) => (
                  <li key={`${line.key}-${line.id ?? "none"}`} className="flex justify-between gap-4 p-3 text-sm">
                    <span>{line.name}</span>
                    <span>{formatEgp(line.amount)}</span>
                  </li>
                ))}
              </ul>
            )}
          </section>
        );
    }
  };

  const renderSummary = () => (
    <aside aria-label="Design summary" className="rounded-xl border border-border bg-card p-5">
      <h2 className="text-xl">Your design</h2>
      <p className="mt-1 text-sm text-muted-foreground">
        {validation.status === "validating" ? "Updating estimate…" : validation.error ?? "Live estimate"}
      </p>
      <p className="mt-3 text-3xl font-display" aria-live="polite">
        {price ? formatEgp(price.total) : "—"}
      </p>
      {price && (
        <ul className="mt-4 space-y-2 border-t border-border pt-3">
          {price.items.map((line) => (
            <li key={`${line.key}-${line.id ?? "none"}`} className="flex justify-between gap-3 text-sm">
              <span>{line.name}</span>
              <span className="shrink-0">{formatEgp(line.amount)}</span>
            </li>
          ))}
        </ul>
      )}
      <p className="mt-4 text-xs text-muted-foreground">
        Your final price is confirmed by the server when your design is quoted.
      </p>
    </aside>
  );

  const nextButton = (
    <Button className="min-h-11" onClick={nextStep} disabled={!canGoForward}>
      {currentStep === 5 ? "Review design" : "Next"}
    </Button>
  );
  const backButton = (
    <Button className="min-h-11" variant="outline" onClick={prevStep} disabled={currentStep === 1}>
      Back
    </Button>
  );

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 pb-28 sm:px-6 lg:py-12 lg:pb-12">
      <header className="mb-8 space-y-5">
        <div>
          <p className="text-sm uppercase tracking-[0.2em] text-bronze">The design studio</p>
          <h1 className="text-4xl">Make it yours</h1>
        </div>
        <nav aria-label="Design steps" className="overflow-x-auto">
          <ol className="flex min-w-max gap-2">
            {STEPS.map((step) => {
              const status = getStepStatus(step.id, selections, index);
              const active = currentStep === step.id;
              const reachable = step.id <= highestStep || status === "complete";
              return (
                <li key={step.id}>
                  <button
                    type="button"
                    onClick={() => reachable && goToStep(step.id)}
                    disabled={!reachable}
                    aria-current={active ? "step" : undefined}
                    aria-label={`${step.title}, ${active ? "current step" : status === "complete" ? "complete" : reachable ? "available" : "not yet available"}`}
                    className={`min-h-11 rounded-full border px-4 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 ${
                      active ? "border-primary bg-primary text-primary-foreground" : "border-border bg-card"
                    }`}
                  >
                    <span aria-hidden="true" className="me-1">{status === "complete" ? "✓" : step.id}</span>
                    {step.title}
                  </button>
                </li>
              );
            })}
          </ol>
        </nav>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p role="status" className="text-sm text-muted-foreground">
            {savedAt
              ? `Draft saved on this device · ${new Date(savedAt).toLocaleString()}`
              : "Your draft will be saved on this device"}
          </p>
          {validation.error && <p role="status" className="text-sm text-destructive">{validation.error}</p>}
        </div>
      </header>

      {staleNotice && staleNotice.length > 0 && (
        <div role="status" className="mb-6 flex items-start justify-between gap-4 rounded-lg border border-border bg-accent p-4 text-sm">
          <p>Some unavailable choices were removed from your saved design: {staleNotice.join(", ")}.</p>
          <Button variant="ghost" size="sm" onClick={dismissStaleNotice} aria-label="Dismiss unavailable choices notice">
            Dismiss
          </Button>
        </div>
      )}

      <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="min-w-0 space-y-6">
          <section aria-labelledby="step-title" className="min-h-72">
            <p className="mb-1 text-sm text-bronze">Step {currentStep} of {STEPS.length}</p>
            <h2 id="step-title" className="sr-only">{STEPS[currentStep - 1].title}</h2>
            {renderStep()}
          </section>
          <div className="hidden items-center justify-between gap-4 md:flex">
            {backButton}
            {nextButton}
          </div>
        </div>
        <div className="hidden lg:block">
          <div className="sticky top-24">{renderSummary()}</div>
        </div>
      </div>

      <div className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-background/95 p-3 backdrop-blur md:hidden">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3">
          <div className="min-w-0">
            <p className="text-xs text-muted-foreground">Running total</p>
            <p className="truncate text-lg font-medium" aria-live="polite">{price ? formatEgp(price.total) : "—"}</p>
          </div>
          <div className="flex shrink-0 gap-2">
            {backButton}
            {nextButton}
          </div>
        </div>
      </div>
    </div>
  );
}
