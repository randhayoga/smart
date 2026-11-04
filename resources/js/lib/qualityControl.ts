import { formatDate } from '@/lib/utils';

export function formatQcDate(val: string | Date | null | undefined): string {
  return formatDate(val);
}

/**
 * Returns edit input title:
 * "Quality Control (last: DD-MM-YYYY)" / "Quality Control (terakhir: DD-MM-YYYY)"
 * or simply "Quality Control" if lastQc is null/empty.
 */
export function getQcEditTitle(
  lastQc: string | Date | null | undefined,
  t: (key: string, values?: any) => string
): string {
  if (lastQc) {
    const formatted = formatQcDate(lastQc);
    if (formatted && formatted !== '-') {
      return t('inventory.qualityControlWithDate', { date: formatted });
    }
  }
  return t('inventory.qualityControl');
}

/**
 * Returns formatted QC output string:
 * "QC Pass (at: DD-MM-YYYY)" / "QC Pass (pada: DD-MM-YYYY)"
 * "QC Fail (at: DD-MM-YYYY)" / "QC Fail (pada: DD-MM-YYYY)"
 * or '-' if not set.
 */
export function formatQcStatus(
  qcPass: boolean | null | undefined,
  lastQc: string | Date | null | undefined,
  t: (key: string, values?: any) => string
): string {
  if (qcPass === null || qcPass === undefined) {
    return '-';
  }

  const formattedDate = lastQc ? formatQcDate(lastQc) : null;
  const hasDate = Boolean(formattedDate && formattedDate !== '-');

  if (qcPass) {
    return hasDate
      ? t('inventory.qcPassWithDate', { date: formattedDate })
      : t('inventory.qcPass');
  } else {
    return hasDate
      ? t('inventory.qcFailWithDate', { date: formattedDate })
      : t('inventory.qcFail');
  }
}

/**
 * Tailwind classes for QC status:
 * Pass -> text-emerald-600 (success)
 * Fail -> text-rose-600 (destructive)
 */
export function getQcColorClass(qcPass: boolean | null | undefined): string {
  if (qcPass === true) {
    return 'text-emerald-600 dark:text-emerald-500 font-semibold';
  }
  if (qcPass === false) {
    return 'text-rose-600 dark:text-rose-500 font-semibold';
  }
  return 'text-foreground';
}
