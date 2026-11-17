/**
 * Money arrives from the server as integer nano-dollars (1 USD = 1e9).
 * Costs are fractions of a cent, so we show 4 decimals by default and the
 * exact 9-decimal value on hover.
 */
const NANOS_PER_USD = 1_000_000_000;

export function formatUsd(
    nanos: number | null | undefined,
    decimals = 4,
): string {
    if (nanos === null || nanos === undefined) {
        return '—';
    }

    const usd = nanos / NANOS_PER_USD;
    const sign = usd < 0 ? '-' : '';

    return `${sign}$${Math.abs(usd).toFixed(decimals)}`;
}

/** Exact value, no float rounding: 12345678 → "$0.012345678". */
export function formatUsdExact(nanos: number | null | undefined): string {
    if (nanos === null || nanos === undefined) {
        return '—';
    }

    const sign = nanos < 0 ? '-' : '';
    const digits = Math.abs(nanos).toString().padStart(10, '0');

    return `${sign}$${digits.slice(0, -9)}.${digits.slice(-9)}`;
}
