/**
 * Request Status standardization utilities and visual pill badge styling.
 * Single source of truth for request statuses across the application.
 */

export type RequestStatus = 
  | 'Menunggu approval' 
  | 'Di-approve' 
  | 'Ditolak' 
  | 'Serah Terima' 
  | 'Dipinjam' 
  | 'Selesai' 
  | 'Dibatalkan' 
  | 'Pending' 
  | 'Partial';

export type RawRequestStatus = 
  | 'wait' 
  | 'approve' 
  | 'reject' 
  | 'confirm' 
  | 'handover' 
  | 'borrow' 
  | 'return' 
  | 'success' 
  | 'cancel' 
  | 'pending' 
  | 'partial';

/**
 * Standard pill badge base styling (one step larger than xs: text-sm, px-3 py-1).
 */
export const REQUEST_STATUS_PILL_BASE = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold';

/**
 * Normalizes raw or legacy status keys to standard Indonesian display labels.
 *
 * @param rawOrStatus - Raw database status key or localized status string
 * @returns Standardized label
 */
export function getRequestStatusLabel(rawOrStatus: string | null | undefined): string {
  if (!rawOrStatus) return '-';
  const s = rawOrStatus.trim().toLowerCase();

  if (s === 'wait' || s === 'menunggu approval' || s === 'menunggu persetujuan') {
    return 'Menunggu approval';
  }
  if (s === 'approve' || s === 'approved' || s === 'disetujui' || s === 'di-approve' || s === 'diapprove' || s === 'di-approve manager') {
    return 'Di-approve';
  }
  if (s === 'reject' || s === 'rejected' || s === 'ditolak') {
    return 'Ditolak';
  }
  if (s === 'confirm' || s === 'handover' || s === 'serah terima' || s === 'dikonfirmasi' || s === 'dikonfirmasi admin') {
    return 'Serah Terima';
  }
  if (s === 'borrow' || s === 'return' || s === 'dipinjam' || s === 'sedang dipinjam') {
    return 'Dipinjam';
  }
  if (s === 'success' || s === 'selesai' || s === 'sukses') {
    return 'Selesai';
  }
  if (s === 'cancel' || s === 'dibatalkan') {
    return 'Dibatalkan';
  }
  if (s === 'pending') {
    return 'Pending';
  }
  if (s === 'partial' || s === 'disetujui sebagian (partial)') {
    return 'Partial';
  }

  return rawOrStatus;
}

/**
 * Returns Tailwind CSS color classes for the request status badge.
 *
 * @param status - Raw or formatted request status
 * @returns Tailwind CSS color classes
 */
export function getRequestStatusBadgeClass(status: string | null | undefined): string {
  const label = getRequestStatusLabel(status);

  switch (label) {
    case 'Menunggu approval':
      return 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
    case 'Di-approve':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300';
    case 'Ditolak':
      return 'bg-destructive/10 text-destructive dark:bg-destructive/20 border border-destructive/20';
    case 'Selesai':
      return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
    case 'Serah Terima':
      return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300';
    case 'Dipinjam':
      return 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300';
    case 'Dibatalkan':
      return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
    case 'Pending':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300';
    case 'Partial':
      return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-300';
    default:
      return 'bg-muted text-muted-foreground border-border';
  }
}

/**
 * Returns full combined pill classes (base sizing + color scheme).
 *
 * @param status - Raw or formatted request status
 * @returns Combined CSS classes string
 */
export function getRequestStatusPillClass(status: string | null | undefined): string {
  return `${REQUEST_STATUS_PILL_BASE} ${getRequestStatusBadgeClass(status)}`;
}
