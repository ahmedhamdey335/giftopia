"use client";

import { useRef, useState } from "react";
import Image from "next/image";
import { AlertTriangle, CheckCircle2, ShieldAlert, Upload, X } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { formatDate } from "@/features/orders/status";
import { ordersApi, type Order, type RefundRequest } from "@/features/orders/orders-api";
import { useOrderRefunds } from "@/features/orders/use-orders";
import { errorMessage } from "@/lib/api/errors";
import { formatEgp } from "@/lib/money";

interface RefundDialogProps {
  order: Order;
  onUpdated?: () => void;
}

export function RefundSection({ order, onUpdated }: RefundDialogProps) {
  const { data, mutate } = useOrderRefunds(order.id);
  const [modalOpen, setModalOpen] = useState(false);

  const [reason, setReason] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const [previews, setPreviews] = useState<string[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const refundRequests = data?.items ?? [];
  const isDelivered = order.status === "delivered";

  // If order is not delivered and has no refund requests, do not render section.
  if (!isDelivered && refundRequests.length === 0) {
    return null;
  }

  function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const selected = Array.from(e.target.files ?? []);
    if (files.length + selected.length > 3) {
      toast.error("You can upload at most 3 evidence photos.");
      return;
    }

    const validFiles: File[] = [];
    const newPreviews: string[] = [];

    for (const file of selected) {
      if (file.size > 5 * 1024 * 1024) {
        toast.error(`${file.name} exceeds 5 MB.`);
        continue;
      }
      validFiles.push(file);
      newPreviews.push(URL.createObjectURL(file));
    }

    setFiles((prev) => [...prev, ...validFiles]);
    setPreviews((prev) => [...prev, ...newPreviews]);
  }

  function removeFile(index: number) {
    setFiles((prev) => prev.filter((_, i) => i !== index));
    setPreviews((prev) => {
      URL.revokeObjectURL(prev[index]);
      return prev.filter((_, i) => i !== index);
    });
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!reason.trim() || reason.trim().length < 10) {
      toast.error("Please explain the issue (at least 10 characters).");
      return;
    }

    setIsSubmitting(true);
    try {
      let evidenceIds: string[] = [];
      if (files.length > 0) {
        const uploaded = await ordersApi.uploadRefundEvidence(order.id, files);
        evidenceIds = uploaded.map((item) => item.id);
      }

      await ordersApi.createRefundRequest(order.id, reason.trim(), evidenceIds);
      toast.success("Refund claim submitted. Our team will review your photos and notes.");
      setModalOpen(false);
      setReason("");
      setFiles([]);
      setPreviews([]);
      await mutate();
      onUpdated?.();
    } catch (err) {
      toast.error(errorMessage(err, "Failed to submit refund claim."));
    } finally {
      setIsSubmitting(false);
    }
  }

  const hasPendingClaim = refundRequests.some((r) => r.status === "requested");

  return (
    <>
      <section aria-labelledby="refunds-heading" className="rounded-2xl border border-border bg-card p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <ShieldAlert aria-hidden className="size-5 text-bronze" />
            <h2 id="refunds-heading" className="text-xl font-medium">
              Resolution & refunds
            </h2>
          </div>
          {isDelivered && !hasPendingClaim && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setModalOpen(true)}
            >
              Report an issue
            </Button>
          )}
        </div>

        {refundRequests.length > 0 ? (
          <div className="mt-4 flex flex-col gap-3">
            {refundRequests.map((claim: RefundRequest) => (
              <div key={claim.id} className="rounded-xl border border-border/80 bg-muted/30 p-4 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="font-medium text-foreground">
                    Claim #{claim.id} · {formatDate(claim.created_at)}
                  </span>
                  <RefundStatusBadge status={claim.status} />
                </div>
                <p className="mt-2 text-muted-foreground">“{claim.reason}”</p>

                {claim.decision_note && (
                  <div className="mt-2 rounded-lg bg-background p-2.5 text-xs text-foreground">
                    <strong>Decision note: </strong> {claim.decision_note}
                  </div>
                )}

                {claim.approved_amount !== null && claim.approved_amount > 0 && (
                  <p className="mt-2 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                    Approved refund: {formatEgp(claim.approved_amount)}
                  </p>
                )}

                {claim.evidence?.length > 0 && (
                  <div className="mt-3 flex gap-2">
                    {claim.evidence.map((photo, i) => (
                      <a
                        key={photo.id || i}
                        href={photo.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="relative size-14 overflow-hidden rounded-lg border border-border bg-muted"
                      >
                        <Image src={photo.url} alt="Evidence photo" fill className="object-cover" />
                      </a>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        ) : (
          <p className="mt-2 text-xs text-muted-foreground">
            Delivered orders include a 3-day window for transit damage or craftsmanship defects.
          </p>
        )}
      </section>

      {/* Refund Claim Dialog */}
      {modalOpen && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="claim-dialog-title"
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          onClick={(e) => {
            if (e.target === e.currentTarget && !isSubmitting) setModalOpen(false);
          }}
        >
          <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-border bg-background p-6 shadow-xl">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 id="claim-dialog-title" className="text-2xl font-serif">
                  Report a defect or damage
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                  Order #{order.order_number} · Delivered items can be claimed within 3 days.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setModalOpen(false)}
                className="rounded-lg p-1 text-muted-foreground hover:bg-muted"
              >
                <X className="size-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="mt-5 flex flex-col gap-4">
              <div className="rounded-xl bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300">
                <AlertTriangle className="mr-1.5 inline size-4 shrink-0" />
                Handmade resin naturally features organic micro-bubbles and unique botanical positioning. Claims cover
                structural cracks, transit breakage, or lettering errors.
              </div>

              <div className="flex flex-col gap-1.5">
                <label htmlFor="claim-reason" className="text-xs font-medium text-foreground">
                  Describe what happened (min 10 characters)
                </label>
                <textarea
                  id="claim-reason"
                  rows={4}
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  placeholder="e.g. The coaster arrived with a crack on the edge from transit."
                  className="w-full rounded-xl border border-border bg-card p-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
              </div>

              <div className="flex flex-col gap-2">
                <label className="text-xs font-medium text-foreground">
                  Attach photos (up to 3 photos, max 5 MB each)
                </label>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  multiple
                  onChange={handleFileSelect}
                  className="hidden"
                />

                <div className="flex flex-wrap gap-2">
                  {previews.map((url, index) => (
                    <div key={url} className="group relative size-20 overflow-hidden rounded-xl border border-border">
                      <Image src={url} alt="Upload preview" fill className="object-cover" />
                      <button
                        type="button"
                        onClick={() => removeFile(index)}
                        className="absolute right-1 top-1 rounded-full bg-black/70 p-1 text-white hover:bg-black"
                      >
                        <X className="size-3" />
                      </button>
                    </div>
                  ))}

                  {files.length < 3 && (
                    <button
                      type="button"
                      onClick={() => fileInputRef.current?.click()}
                      className="flex size-20 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-border bg-muted/40 text-xs text-muted-foreground hover:bg-muted"
                    >
                      <Upload className="size-4" />
                      Add photo
                    </button>
                  )}
                </div>
              </div>

              <div className="mt-4 flex justify-end gap-3">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setModalOpen(false)}
                  disabled={isSubmitting}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={isSubmitting || reason.trim().length < 10}
                >
                  {isSubmitting ? "Submitting…" : "Submit claim"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}

function RefundStatusBadge({ status }: { status: RefundRequest["status"] }) {
  switch (status) {
    case "approved":
    case "refunded":
      return (
        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
          <CheckCircle2 className="size-3" /> {status}
        </span>
      );
    case "rejected":
    case "failed":
      return (
        <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
          <X className="size-3" /> {status}
        </span>
      );
    default:
      return (
        <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
          <AlertTriangle className="size-3" /> Under review
        </span>
      );
  }
}
