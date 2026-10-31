/**
 * timelineNoteLocalization.ts
 *
 * Pure helper for identifying and localizing backend request status log notes
 * in the front-end stepper (especially manual borrowing finish and return actions),
 * returning appropriate step title and localized HTML-formatted description.
 */

export interface LocalizedTimelineLog {
  title?: string;
  description: string;
}

export function parseAndLocalizeTimelineNote(
  note: string | null | undefined,
  t: (key: string, params?: Record<string, any>) => string,
  isAssignment = false
): LocalizedTimelineLog | null {
  if (!note || typeof note !== 'string') {
    return null;
  }

  const trimmed = note.trim();

  // 1. Finish All Manual Borrowing / Assignment
  // Examples:
  // - "Seluruh peminjaman diselesaikan secara manual oleh Admin Alice di Ruang IFS."
  // - "Seluruh penugasan diselesaikan secara manual oleh Admin Alice di Ruang IFS."
  // - "Seluruh peminjaman diselesaikan secara manual oleh Admin di Ruang IFS. Seluruh aset berstatus Tersedia."
  const finishAllRegex = /^Seluruh\s+(peminjaman|penugasan)\s+diselesaikan\s+secara\s+manual\s+oleh\s+Admin(?:\s+([^.\n]+?))?\s+di\s+([^.\n]+?)(?:\.\s*Seluruh aset berstatus Tersedia|\.|$)/i;
  const matchAll = trimmed.match(finishAllRegex);
  if (matchAll) {
    const actionWord = matchAll[1].toLowerCase();
    const isAssign = isAssignment || actionWord === 'penugasan';
    const adminRaw = (matchAll[2] || '').trim() || 'Admin';
    const locationRaw = (matchAll[3] || '').trim();

    const nameSpan = `<span class="font-bold text-foreground">${adminRaw}</span>`;
    const locSpan = locationRaw ? `<span class="font-bold text-foreground">${locationRaw}</span>` : '';

    return {
      title: isAssign
        ? t('fulfillment.manualAssignmentCompleted')
        : t('fulfillment.manualBorrowingCompleted'),
      description: isAssign
        ? t('fulfillment.allAssignmentFinishedNote', { name: nameSpan, location: locSpan })
        : t('fulfillment.allBorrowingFinishedNote', { name: nameSpan, location: locSpan }),
    };
  }

  // 2. Finish Single Asset Manual Borrowing / Assignment
  // Examples:
  // - "Peminjaman aset AST-FIN-1 diselesaikan oleh Admin Alice di Ruang Server."
  // - "Penugasan aset AST-FIN-1 diselesaikan oleh Admin Alice di Ruang Server."
  // - "Peminjaman aset AST-FIN-1 diselesaikan oleh Admin di Ruang Server. Aset berstatus Tersedia."
  const finishAssetRegex = /^(Peminjaman|Penugasan)\s+aset\s+([^.\n]+?)\s+diselesaikan\s+oleh\s+Admin(?:\s+([^.\n]+?))?\s+di\s+([^.\n]+?)(?:\.\s*(?:Seluruh\s+)?aset\s+(?:telah\s+)?berstatus Tersedia|\.|$)/i;
  const matchAsset = trimmed.match(finishAssetRegex);
  if (matchAsset) {
    const actionWord = matchAsset[1].toLowerCase();
    const isAssign = isAssignment || actionWord === 'penugasan';
    const assetCode = matchAsset[2].trim();
    const adminRaw = (matchAsset[3] || '').trim() || 'Admin';
    const locationRaw = (matchAsset[4] || '').trim();

    const codeSpan = `<span class="font-bold text-foreground">${assetCode}</span>`;
    const nameSpan = `<span class="font-bold text-foreground">${adminRaw}</span>`;
    const locSpan = locationRaw ? `<span class="font-bold text-foreground">${locationRaw}</span>` : '';

    return {
      title: t('fulfillment.manualAssetReturnCompleted'),
      description: isAssign
        ? t('fulfillment.assetAssignmentFinishedNote', { code: codeSpan, name: nameSpan, location: locSpan })
        : t('fulfillment.assetBorrowingFinishedNote', { code: codeSpan, name: nameSpan, location: locSpan }),
    };
  }

  // 3. Initial Manual Confirmation Logs
  if (trimmed.includes('Peminjaman manual (ST) dikonfirmasi oleh Admin')) {
    return {
      title: t('fulfillment.confirmed'),
      description: t('fulfillment.manualStConfirmedNote'),
    };
  }
  if (trimmed.includes('Peminjaman (sewa) manual dikonfirmasi oleh Admin')) {
    return {
      title: t('fulfillment.confirmed'),
      description: t('fulfillment.manualRentConfirmedNote'),
    };
  }
  if (trimmed.includes('Assignment manual (LT) dikonfirmasi oleh Admin')) {
    return {
      title: t('fulfillment.confirmed'),
      description: t('fulfillment.manualLtConfirmedNote'),
    };
  }
  if (trimmed.includes('Permintaan manual habis pakai dikonfirmasi oleh Admin')) {
    return {
      title: t('fulfillment.confirmed'),
      description: t('fulfillment.manualConsumableConfirmedNote'),
    };
  }

  // 4. Return Confirmation
  if (trimmed.startsWith('Pengembalian aset dikonfirmasi oleh Admin')) {
    return {
      title: t('fulfillment.returnCompleted'),
      description: t('fulfillment.returnConfirmedStorageNote'),
    };
  }

  return null;
}
