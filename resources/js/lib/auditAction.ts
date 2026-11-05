/**
 * Audit Action standardization and localization utilities.
 * Single source of truth for audit trail actions across the application.
 */
import { h, type VNode } from 'vue';
import { router } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
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
  'deletion': 'admin.auditActionTypes.deaktivasi',
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
  'assignment': 'admin.auditActionTypes.assignment',
  'penetapan': 'admin.auditActionTypes.assignment',
  'disapproval': 'admin.auditActionTypes.disapproval',
  'penolakan': 'admin.auditActionTypes.disapproval',
  'quality control': 'admin.auditActionTypes.qualityControl',
  'quality_control': 'admin.auditActionTypes.qualityControl',
  'qc': 'admin.auditActionTypes.qualityControl',
  'pemeriksaan qc': 'admin.auditActionTypes.qualityControl',
  'perubahan qc': 'admin.auditActionTypes.qualityControl',
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

/**
 * Renders an audit log note, automatically turning any associated request number
 * into an interactive, clickable SPA link to the request detail page.
 */
export function renderAuditNote(
  note: string | null | undefined,
  requestId?: number | null,
  requestNumber?: string | null
): VNode | string {
  if (!note || note === '-') return '-';

  const formatLine = (line: string): (string | VNode)[] => {
    if (!requestId) return [line];

    const targetNumber = requestNumber || line.match(/\b\d{6}-\d{4}\b/)?.[0];
    if (!targetNumber || !line.includes(targetNumber)) {
      return [line];
    }

    const idx = line.indexOf(targetNumber);
    const before = line.substring(0, idx);
    const after = line.substring(idx + targetNumber.length);

    const linkVNode = h(
      'a',
      {
        href: `/smart/requests/${requestId}`,
        class: 'text-primary font-semibold hover:underline inline-flex items-center gap-1 cursor-pointer no-underline',
        onClick: (e: MouseEvent) => {
          e.preventDefault();
          e.stopPropagation();
          router.visit(`/smart/requests/${requestId}`);
        },
      },
      [
        targetNumber,
        h(ExternalLink, { class: 'inline-block h-3 w-3 text-primary/70 shrink-0' }),
      ]
    );

    return [before, linkVNode, after];
  };

  if (note.includes(' | ')) {
    const lines = note.split(' | ');
    return h(
      'ul',
      { class: 'list-disc pl-4 space-y-0.5 text-muted-foreground whitespace-normal text-left min-w-[200px]' },
      lines.map((line) => h('li', {}, formatLine(line)))
    );
  }

  return h(
    'div',
    { class: 'text-muted-foreground whitespace-normal text-left min-w-[200px]' },
    formatLine(note)
  );
}
