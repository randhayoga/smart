/**
 * Centralized asset status parsing, multi-status helpers, badge styling, and i18n label resolution.
 */

export const STATUS_KEY_MAP: Record<string, string> = {
  'tersedia': 'status.tersedia',
  'available': 'status.tersedia',
  'dipinjam': 'status.dipinjam',
  'tidak aktif': 'status.tidakAktif',
  'pending': 'status.pending',
  'pending:dm': 'status.pendingDm',
  'pending: dm': 'status.pendingDm',
  'pending:manager': 'status.pendingDm',
  'pending: manager': 'status.pendingDm',
  'pending:bod/boc': 'status.pendingBodBoc',
  'pending: bod/boc': 'status.pendingBodBoc',
  'bagus': 'status.bagus',
  'rusak': 'status.rusak',
  'rusak total': 'status.rusakTotal',
  'hilang': 'status.hilang',
  'dihapus': 'status.dihapus',
  'ditolak': 'status.ditolak',
  'rejected': 'status.ditolak',
  'disetujui': 'status.disetujui',
  'approved': 'status.disetujui',
  'sukses': 'status.sukses',
  'success': 'status.sukses',
  'belum diverifikasi': 'status.belumDiverifikasi',
  'unverified': 'status.belumDiverifikasi',
  'verifikasi ditolak': 'status.verifikasiDitolak',
  'verification rejected': 'status.verifikasiDitolak',
  'menunggu approval': 'status.menungguApproval',
  'menunggu persetujuan': 'status.menungguApproval',
  'di-approve': 'status.diApprove',
  'diapprove': 'status.diApprove',
  'dikonfirmasi admin': 'status.dikonfirmasiAdmin',
  'dikonfirmasi': 'status.dikonfirmasiAdmin',
  'menunggu serah terima': 'status.menungguSerahTerima',
  'menunggu_serah_terima': 'status.menungguSerahTerima',
  'serah terima': 'status.serahTerima',
  'handover': 'status.serahTerima',
  'selesai': 'status.selesai',
  'dibatalkan': 'status.dibatalkan',
  'cancel': 'status.dibatalkan',
  'parsial': 'status.parsial',
  'partial': 'status.parsial',
};

/**
 * Split a comma-separated status string into trimmed non-empty tokens.
 */
export function parseAssetStatuses(rawStatus?: string | null): string[] {
  if (!rawStatus) return [];
  return String(rawStatus)
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);
}

/**
 * Check if an asset status string contains a specific status token (case-insensitive).
 */
export function hasAssetStatus(rawStatus?: string | null, target?: string | null): boolean {
  if (!rawStatus || !target) return false;
  const targetLower = target.trim().toLowerCase();
  return parseAssetStatuses(rawStatus).some((s) => s.toLowerCase() === targetLower);
}

/**
 * Extract the primary base status (excluding Pending:* tokens), defaulting to 'Tersedia'.
 */
export function getBaseAssetStatus(rawStatus?: string | null): string {
  const tokens = parseAssetStatuses(rawStatus);
  for (const token of tokens) {
    if (!token.toLowerCase().startsWith('pending')) {
      return token;
    }
  }
  return 'Tersedia';
}

/**
 * Check if any status token on the asset restricts manual status/condition edits.
 */
export function isRestrictedAssetStatus(rawStatus?: string | null): boolean {
  const tokens = parseAssetStatuses(rawStatus);
  if (tokens.length === 0) return false;
  return tokens.some((token) => {
    const s = token.toLowerCase();
    return (
      s === 'tidak aktif' ||
      s === 'dihapus' ||
      s === 'pending' ||
      s.startsWith('pending') ||
      s === 'belum diverifikasi' ||
      s === 'verifikasi ditolak'
    );
  });
}

/**
 * Resolve visual pill badge classes for a single status token.
 */
export function getAssetStatusBadgeClass(status?: string | null): string {
  const s = status?.trim();
  if (!s) return 'bg-gray-100 text-gray-800';
  const lower = s.toLowerCase();

  if (s === 'Belum Diverifikasi' || lower === 'belum diverifikasi' || lower === 'unverified') {
    return 'bg-amber-50 text-amber-900 border border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-700';
  }
  if (s === 'Verifikasi Ditolak' || lower === 'verifikasi ditolak' || lower === 'verification rejected') {
    return 'bg-rose-50 text-rose-900 border border-rose-300 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-700';
  }
  if (s === 'Tersedia' || lower === 'tersedia' || lower === 'available') return 'bg-emerald-100 text-emerald-800';
  if (s === 'Dipinjam' || lower === 'dipinjam') return 'bg-amber-100 text-amber-800';
  if (s === 'Tidak Aktif' || lower === 'tidak aktif') return 'bg-gray-200 text-gray-800';
  if (s === 'Pending' || lower === 'pending' || lower.startsWith('pending')) return 'bg-purple-100 text-purple-800';
  if (s === 'Bagus' || lower === 'bagus') return 'bg-emerald-100 text-emerald-800';
  if (s === 'Rusak' || lower === 'rusak') return 'bg-rose-100 text-rose-800';
  if (s === 'Rusak Total' || lower === 'rusak total') return 'bg-red-100 text-red-800';
  if (s === 'Hilang' || lower === 'hilang') return 'bg-rose-100 text-rose-800';
  if (lower === 'dihapus' || lower === 'ditolak' || lower === 'rejected' || lower === 'cancel' || lower === 'dibatalkan') return 'bg-rose-100 text-rose-800';
  if (lower === 'disetujui' || lower === 'approved' || lower === 'sukses' || lower === 'success') return 'bg-emerald-100 text-emerald-800';
  return 'bg-gray-100 text-gray-800';
}

/**
 * Resolve localized label for a single status token.
 */
export function getAssetStatusLabel(
  status?: string | null,
  t?: (key: string) => string,
  te?: (key: string) => boolean,
): string {
  if (!status) return '';
  const trimmed = status.trim();
  const key = STATUS_KEY_MAP[trimmed.toLowerCase()];
  if (key && t && (!te || te(key))) {
    const translated = t(key);
    if (translated && translated !== key) {
      return translated;
    }
  }
  return trimmed;
}

/**
 * Format a potentially comma-separated status string into comma-separated localized labels.
 */
export function formatAssetStatusLabels(
  rawStatus?: string | null,
  t?: (key: string) => string,
  te?: (key: string) => boolean,
): string {
  const tokens = parseAssetStatuses(rawStatus);
  if (tokens.length === 0) return '';
  return tokens.map((token) => getAssetStatusLabel(token, t, te)).join(', ');
}
