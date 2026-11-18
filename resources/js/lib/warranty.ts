import { formatDate } from './utils';

/**
 * Normalizes a warranty date string to 'YYYY-MM-DD' for safe local date comparison.
 */
function toIsoDatePart(val: string | null | undefined): string | null {
  if (!val) return null;
  const trimmed = val.trim();
  const match = trimmed.match(/^(\d{4})[-/](\d{2})[-/](\d{2})/);
  if (match) {
    return `${match[1]}-${match[2]}-${match[3]}`;
  }
  const d = new Date(trimmed);
  if (isNaN(d.getTime())) return null;
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/**
 * Returns today's local date as 'YYYY-MM-DD'.
 */
function getTodayIsoDate(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/**
 * Checks whether an asset's warranty is currently active (expiry date >= today).
 */
export function isWarrantyActive(warrantyDate: string | null | undefined): boolean {
  const iso = toIsoDatePart(warrantyDate);
  if (!iso) return false;
  return iso >= getTodayIsoDate();
}

/**
 * Returns the semantic color class for warranty date display:
 * - Active (>= today) -> green (success)
 * - Inactive (< today) -> red (destructive)
 * - Null/empty -> default (no extra color class)
 */
export function getWarrantyColorClass(warrantyDate: string | null | undefined): string {
  const iso = toIsoDatePart(warrantyDate);
  if (!iso) return '';
  return iso >= getTodayIsoDate()
    ? 'text-emerald-600 dark:text-emerald-500 font-semibold'
    : 'text-destructive font-semibold';
}

/**
 * Formats warranty date for UI display ('DD-MM-YYYY' or '-' when null).
 */
export function formatWarrantyDate(warrantyDate: string | null | undefined): string {
  if (!warrantyDate) return '-';
  return formatDate(warrantyDate);
}
