/**
 * Request Status standardization utilities and visual pill badge styling.
 * Single source of truth for request statuses across the application.
 */
import { i18n } from '@/locales';

export type RequestStatus = 
  | 'Menunggu approval' 
  | 'Di-approve' 
  | 'Ditolak' 
  | 'Dikonfirmasi Admin'
  | 'Menunggu Serah Terima'
  | 'Serah Terima' 
  | 'Dipinjam' 
  | 'Selesai' 
  | 'Dibatalkan' 
  | 'Pending' 
  | 'Partial'
  | 'Parsial';

export type RawRequestStatus = 
  | 'wait' 
  | 'approve' 
  | 'reject' 
  | 'confirm' 
  | 'menunggu_serah_terima'
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
  if (s === 'confirm' || s === 'dikonfirmasi' || s === 'dikonfirmasi admin') {
    return 'Dikonfirmasi Admin';
  }
  if (s === 'menunggu_serah_terima' || s === 'menunggu serah terima') {
    return 'Menunggu Serah Terima';
  }
  if (s === 'handover' || s === 'serah terima') {
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
  if (s === 'partial' || s === 'parsial' || s === 'disetujui sebagian (partial)') {
    return 'Parsial';
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
    case 'Dikonfirmasi Admin':
      return 'bg-teal-100 text-teal-800 dark:bg-teal-950/40 dark:text-teal-300';
    case 'Selesai':
      return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
    case 'Menunggu Serah Terima':
    case 'Serah Terima':
      return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300';
    case 'Dipinjam':
      return 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300';
    case 'Dibatalkan':
      return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
    case 'Pending':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300';
    case 'Parsial':
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

/**
 * Splits a comma-separated status string into individual status tokens.
 */
export function parseRequestStatuses(rawStatus: string | null | undefined): string[] {
  if (!rawStatus) return [];
  return rawStatus.split(',').map(s => s.trim()).filter(Boolean);
}

export const REQUEST_STATUS_KEY_MAP: Record<string, string> = {
  'Menunggu approval': 'status.menungguApproval',
  'Di-approve': 'status.diApprove',
  'Ditolak': 'status.ditolak',
  'Dikonfirmasi Admin': 'status.dikonfirmasiAdmin',
  'Menunggu Serah Terima': 'status.menungguSerahTerima',
  'Serah Terima': 'status.serahTerima',
  'Dipinjam': 'status.dipinjam',
  'Selesai': 'status.selesai',
  'Dibatalkan': 'status.dibatalkan',
  'Pending': 'status.pending',
  'Parsial': 'status.parsial',
  'Partial': 'status.parsial',
};

/**
 * Returns localized display label for the request status based on active i18n locale.
 */
export function getLocalizedRequestStatusLabel(rawOrStatus: string | null | undefined): string {
  const canonical = getRequestStatusLabel(rawOrStatus);
  const key = REQUEST_STATUS_KEY_MAP[canonical];
  if (key && (i18n.global as any).te && (i18n.global as any).te(key)) {
    return (i18n.global as any).t(key);
  }
  return canonical;
}

/**
 * Returns an array of badge metadata for multi-status strings.
 */
export function getRequestStatusBadges(rawStatus: string | null | undefined): { label: string; class: string; pillClass: string }[] {
  const parts = parseRequestStatuses(rawStatus);
  if (parts.length === 0) {
    const label = getRequestStatusLabel(rawStatus);
    return [{
      label: getLocalizedRequestStatusLabel(label),
      class: getRequestStatusBadgeClass(label),
      pillClass: getRequestStatusPillClass(label),
    }];
  }

  return parts.map(part => {
    const label = getRequestStatusLabel(part);
    return {
      label: getLocalizedRequestStatusLabel(label),
      class: getRequestStatusBadgeClass(label),
      pillClass: getRequestStatusPillClass(label),
    };
  });
}
