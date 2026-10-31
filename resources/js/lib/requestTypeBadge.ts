/**
 * Shared utility for request/borrowing type labels and status-consistent badge styles.
 *
 * Types:
 * - 'consumable' | 'request' | 'permintaan' -> Red / Rose (matching Stock Out)
 * - 'st' | 'dipinjam (st)' | 'borrowing (st)' -> Amber / Yellow (matching Dipinjam (ST))
 * - 'rent' | 'dipinjam (sewa)' | 'borrowing (rent)' -> Indigo (matching Dipinjam (sewa))
 * - 'lt' | 'di-assign (lt)' | 'assignment (lt)' -> Sky / Blue (matching Di-assign (LT))
 */

export function getRequestTypeBadgeClass(typeKey?: string | null): string {
  const key = typeKey?.trim().toLowerCase();
  switch (key) {
    case 'consumable':
    case 'request':
    case 'permintaan':
      return 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-700';
    case 'st':
    case 'dipinjam (st)':
    case 'borrowing (st)':
    case 'peminjaman (st)':
      return 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-700';
    case 'rent':
    case 'dipinjam (sewa)':
    case 'borrowing (rent)':
    case 'peminjaman (sewa)':
      return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-700';
    case 'lt':
    case 'di-assign (lt)':
    case 'assignment (lt)':
    case 'assignment':
      return 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-700';
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
  }
}

export function getRequestTypeLabel(typeKey: string | null | undefined, t: (key: string) => string): string {
  const key = typeKey?.trim().toLowerCase();
  switch (key) {
    case 'consumable':
    case 'request':
    case 'permintaan':
      return t('fulfillment.request');
    case 'st':
    case 'dipinjam (st)':
    case 'borrowing (st)':
    case 'peminjaman (st)':
      return t('fulfillment.manualRequest.typeSt');
    case 'rent':
    case 'dipinjam (sewa)':
    case 'borrowing (rent)':
    case 'peminjaman (sewa)':
      return t('fulfillment.manualRequest.typeRent');
    case 'lt':
    case 'di-assign (lt)':
    case 'assignment (lt)':
    case 'assignment':
      return t('fulfillment.manualRequest.typeLt');
    default:
      return typeKey || '-';
  }
}

/**
 * Check if the type is long term assignment ('lt')
 */
export function isAssignmentType(typeKey?: string | null): boolean {
  const key = typeKey?.trim().toLowerCase();
  return key === 'lt' || key === 'di-assign (lt)' || key === 'assignment (lt)' || key === 'assignment';
}

/**
 * Get action label: Assignment / Penugasan (for lt), or Borrowing / Peminjaman (for st/rent).
 */
export function getTypeActionLabel(
  typeKey: string | null | undefined,
  t: (key: string, params?: Record<string, any>) => string,
  lower = false
): string {
  if (isAssignmentType(typeKey)) {
    return lower ? t('fulfillment.assignActionLower') : t('fulfillment.assignAction');
  }
  return lower ? t('fulfillment.borrowActionLower') : t('fulfillment.borrowAction');
}

/**
 * Get active status badge text: 'Di-assign' / 'Assigned' (for lt) vs 'Dipinjam' / 'Borrowed' (for st/rent)
 */
export function getActiveStatusBadge(typeKey: string | null | undefined, t: (key: string) => string): string {
  if (isAssignmentType(typeKey)) {
    return t('fulfillment.assignedBadge');
  }
  return t('fulfillment.borrowedBadge');
}
