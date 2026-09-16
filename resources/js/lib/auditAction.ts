/**
 * Audit Action standardization and localization utilities.
 * Single source of truth for audit trail actions across the application.
 */
import { i18n } from '@/locales';

export const AUDIT_ACTION_KEY_MAP: Record<string, string> = {
  'registrasi': 'admin.auditActionTypes.registrasi',
  'registration': 'admin.auditActionTypes.registrasi',
  'perubahan status': 'admin.auditActionTypes.perubahanStatus',
  'perubahan_status': 'admin.auditActionTypes.perubahanStatus',
  'status change': 'admin.auditActionTypes.perubahanStatus',
  'perubahan kondisi': 'admin.auditActionTypes.perubahanKondisi',
  'perubahan_kondisi': 'admin.auditActionTypes.perubahanKondisi',
  'condition change': 'admin.auditActionTypes.perubahanKondisi',
  'pemindahan': 'admin.auditActionTypes.pemindahan',
  'relocation': 'admin.auditActionTypes.pemindahan',
  'transfer': 'admin.auditActionTypes.pemindahan',
  'peminjaman': 'admin.auditActionTypes.peminjaman',
  'borrowing': 'admin.auditActionTypes.peminjaman',
  'loan': 'admin.auditActionTypes.peminjaman',
  'pengembalian': 'admin.auditActionTypes.pengembalian',
  'return': 'admin.auditActionTypes.pengembalian',
  'pemeliharaan': 'admin.auditActionTypes.pemeliharaan',
  'maintenance': 'admin.auditActionTypes.pemeliharaan',
  'approval': 'admin.auditActionTypes.approval',
  'persetujuan': 'admin.auditActionTypes.approval',
  'penghapusan': 'admin.auditActionTypes.penghapusan',
  'disposal': 'admin.auditActionTypes.penghapusan',
  'deaktivasi': 'admin.auditActionTypes.deaktivasi',
  'deactivation': 'admin.auditActionTypes.deaktivasi',
  'penyesuaian': 'admin.auditActionTypes.adjustment',
  'adjustment': 'admin.auditActionTypes.adjustment',
  'stok masuk': 'admin.auditActionTypes.stockIn',
  'stock in': 'admin.auditActionTypes.stockIn',
  'stock_in': 'admin.auditActionTypes.stockIn',
  'stok keluar': 'admin.auditActionTypes.stockOut',
  'stock out': 'admin.auditActionTypes.stockOut',
  'stock_out': 'admin.auditActionTypes.stockOut',
  'create': 'admin.auditActionTypes.create',
  'tambah': 'admin.auditActionTypes.create',
  'update': 'admin.auditActionTypes.update',
  'ubah': 'admin.auditActionTypes.update',
  'delete': 'admin.auditActionTypes.delete',
  'hapus': 'admin.auditActionTypes.delete',
};

/**
 * Normalizes and localizes the audit action string based on the active language.
 *
 * @param action - Raw action string from database/lifecycle log
 * @param t - Optional component-scoped translate function to maintain vue reactivity
 * @returns Localized action string
 */
export function getLocalizedAuditAction(
  action: string | null | undefined,
  t?: (key: string) => string
): string {
  if (!action || action === '-') return '-';
  const key = AUDIT_ACTION_KEY_MAP[action.trim().toLowerCase()];
  if (key) {
    if (t) {
      return t(key);
    }
    if ((i18n.global as any).te && (i18n.global as any).te(key)) {
      return (i18n.global as any).t(key);
    }
  }
  return action;
}
