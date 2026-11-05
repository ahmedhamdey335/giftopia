"use client";

import { useState } from "react";
import Image from "next/image";
import { CheckCircle2, Clock, Eye, MessageSquare, Sparkles } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { formatDate } from "@/features/orders/status";
import { ordersApi, type ProofItem } from "@/features/orders/orders-api";
import { useOrderProofs } from "@/features/orders/use-orders";
import { errorMessage } from "@/lib/api/errors";

interface ProofCardProps {
  orderId: number;
  onUpdated?: () => void;
}

export function ProofCard({ orderId, onUpdated }: ProofCardProps) {
  const { data, mutate, isLoading } = useOrderProofs(orderId);
  const [selectedProof, setSelectedProof] = useState<ProofItem | null>(null);
  const [revisionProof, setRevisionProof] = useState<ProofItem | null>(null);
  const [revisionNote, setRevisionNote] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  const proofs = data?.items ?? [];
  if (isLoading || proofs.length === 0) return null;

  async function handleApprove() {
    setIsSubmitting(true);
    try {
      await ordersApi.approveProof(orderId);
      toast.success("Proof approved! Production is ready to start.");
      await mutate();
      onUpdated?.();
    } catch (err) {
      toast.error(errorMessage(err, "Failed to approve proof."));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleRequestChanges() {
    if (!revisionNote.trim() || revisionNote.trim().length < 10) {
      toast.error("Please provide at least 10 characters explaining what to change.");
      return;
    }
    setIsSubmitting(true);
    try {
      await ordersApi.requestProofChanges(orderId, revisionNote.trim());
      toast.success("Change request submitted. Our artisan will revise your mockup.");
      setRevisionProof(null);
      setRevisionNote("");
      await mutate();
      onUpdated?.();
    } catch (err) {
      toast.error(errorMessage(err, "Failed to submit revision request."));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <>
      <section aria-labelledby="proofs-heading" className="flex flex-col gap-4">
        <div className="flex items-center gap-2">
          <Sparkles aria-hidden className="size-5 text-gold" />
          <h2 id="proofs-heading" className="text-2xl">
            Digital proofs
          </h2>
        </div>

        <div className="flex flex-col gap-4">
          {proofs.map((proof) => {
            const isPending = proof.status === "pending";
            const isApproved = proof.status === "approved";
            const isChanges = proof.status === "changes_requested";

            return (
              <div
                key={`${proof.item_id}-${proof.version}`}
                className="overflow-hidden rounded-2xl border border-border bg-card"
              >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/70 bg-muted/40 px-5 py-3.5">
                  <span className="font-medium text-foreground">Mockup Version {proof.version}</span>
                  {isPending && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                      <Clock className="size-3.5" />
                      Pending your approval
                    </span>
                  )}
                  {isApproved && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                      <CheckCircle2 className="size-3.5" />
                      Approved
                    </span>
                  )}
                  {isChanges && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400">
                      <MessageSquare className="size-3.5" />
                      Revisions in review
                    </span>
                  )}
                </div>

                <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:gap-6">
                  {proof.preview_url ? (
                    <button
                      type="button"
                      onClick={() => setSelectedProof(proof)}
                      className="group relative size-36 shrink-0 overflow-hidden rounded-xl border border-border bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:size-40"
                    >
                      <Image
                        src={proof.preview_url}
                        alt={`Proof mockup version ${proof.version}`}
                        fill
                        className="object-cover transition-transform duration-300 group-hover:scale-105"
                      />
                      <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                        <span className="inline-flex items-center gap-1 text-xs font-medium text-white">
                          <Eye className="size-3.5" /> View
                        </span>
                      </div>
                    </button>
                  ) : (
                    <div className="flex size-36 shrink-0 items-center justify-center rounded-xl border border-dashed border-border bg-muted/30 p-4 text-center text-xs text-muted-foreground sm:size-40">
                      Typography preview
                    </div>
                  )}

                  <div className="flex flex-1 flex-col gap-3">
                    <div>
                      <span className="text-xs uppercase tracking-wider text-muted-foreground">Custom Lettering</span>
                      <p className="text-xl font-medium text-foreground">“{proof.text}”</p>
                    </div>

                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                      {proof.font_name && (
                        <span>
                          Font: <strong className="font-medium text-foreground">{proof.font_name}</strong>
                        </span>
                      )}
                      {proof.text_color_name && (
                        <span>
                          Color: <strong className="font-medium text-foreground">{proof.text_color_name}</strong>
                        </span>
                      )}
                    </div>

                    {proof.note && (
                      <div className="rounded-lg bg-muted/50 p-2.5 text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">Note: </span>
                        {proof.note}
                      </div>
                    )}

                    {isPending && (
                      <div className="mt-2 flex flex-col gap-3">
                        <p className="text-xs text-muted-foreground">
                          Please verify spelling and layout. If no changes are submitted, this proof will auto-approve
                          on <strong>{formatDate(proof.auto_approve_at)}</strong>.
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                          <Button
                            type="button"
                            onClick={handleApprove}
                            disabled={isSubmitting}
                            className="bg-primary text-primary-foreground hover:bg-primary/90"
                          >
                            <CheckCircle2 className="size-4" />
                            Approve proof
                          </Button>
                          <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                              setRevisionProof(proof);
                              setRevisionNote("");
                            }}
                            disabled={isSubmitting}
                          >
                            <MessageSquare className="size-4" />
                            Request revision
                          </Button>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </section>

      {/* Lightbox Modal */}
      {selectedProof?.preview_url && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Proof preview lightbox"
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
          onClick={() => setSelectedProof(null)}
        >
          <div className="relative max-h-[85vh] max-w-2xl overflow-hidden rounded-2xl bg-card p-2 shadow-2xl">
            <Image
              src={selectedProof.preview_url}
              alt="Full size proof mockup"
              width={800}
              height={800}
              className="max-h-[80vh] w-auto rounded-xl object-contain"
            />
            <Button
              type="button"
              variant="secondary"
              size="sm"
              className="mt-2 w-full"
              onClick={() => setSelectedProof(null)}
            >
              Close preview
            </Button>
          </div>
        </div>
      )}

      {/* Revision Dialog */}
      {revisionProof && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="revision-dialog-title"
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          onClick={(e) => {
            if (e.target === e.currentTarget && !isSubmitting) setRevisionProof(null);
          }}
        >
          <div className="w-full max-w-lg rounded-2xl border border-border bg-background p-6 shadow-xl">
            <h3 id="revision-dialog-title" className="text-2xl font-serif">
              Request proof revision
            </h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Describe the adjustments needed for version {revisionProof.version}. Our resin artisan will review your note
              and upload a revised mockup.
            </p>

            <div className="mt-4 flex flex-col gap-2">
              <label htmlFor="revision-note" className="text-xs font-medium text-foreground">
                What should we adjust? (min 10 characters)
              </label>
              <textarea
                id="revision-note"
                rows={4}
                value={revisionNote}
                onChange={(e) => setRevisionNote(e.target.value)}
                placeholder="e.g. Please capitalize the second word and make the script lettering slightly smaller."
                className="w-full rounded-xl border border-border bg-card p-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              />
              <span className="text-end text-xs text-muted-foreground">
                {revisionNote.trim().length} / 300 characters
              </span>
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <Button
                type="button"
                variant="outline"
                onClick={() => setRevisionProof(null)}
                disabled={isSubmitting}
              >
                Cancel
              </Button>
              <Button
                type="button"
                onClick={handleRequestChanges}
                disabled={isSubmitting || revisionNote.trim().length < 10}
              >
                {isSubmitting ? "Submitting…" : "Send change request"}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
