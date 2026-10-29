/** The API sends integer piasters (1 EGP = 100 piasters). Never do float math on money. */
export function formatEgp(piasters: number): string {
  const whole = piasters % 100 === 0;
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "EGP",
    currencyDisplay: "code",
    minimumFractionDigits: whole ? 0 : 2,
    maximumFractionDigits: 2,
  })
    .format(piasters / 100)
    .replace(/\u00a0/g, " ");
}
