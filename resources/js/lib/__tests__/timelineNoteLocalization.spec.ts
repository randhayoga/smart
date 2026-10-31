import { describe, it, expect } from 'vitest';
import { parseAndLocalizeTimelineNote } from '../timelineNoteLocalization';
import enFulfillment from '@/locales/en/fulfillment';
import idFulfillment from '@/locales/id/fulfillment';

describe('timelineNoteLocalization utility', () => {
  const createMockT = (localeMessages: Record<string, any>) => {
    return (key: string, params?: Record<string, any>): string => {
      const parts = key.split('.');
      let val: any = localeMessages;
      for (const part of parts) {
        val = val?.[part];
      }
      if (typeof val !== 'string') return key;
      if (params) {
        let res = val;
        for (const [pKey, pVal] of Object.entries(params)) {
          res = res.replace(new RegExp(`\\{${pKey}\\}`, 'g'), String(pVal));
        }
        return res;
      }
      return val;
    };
  };

  const tEn = createMockT({ fulfillment: enFulfillment });
  const tId = createMockT({ fulfillment: idFulfillment });

  describe('Finish All Manual Borrowing / Assignment', () => {
    it('localizes finish all borrowing note in English and Indonesian', () => {
      const note = 'Seluruh peminjaman diselesaikan secara manual oleh Admin Alice di Ruang IFS.';

      const enRes = parseAndLocalizeTimelineNote(note, tEn, false);
      expect(enRes).not.toBeNull();
      expect(enRes?.title).toBe('Borrowing Completed');
      expect(enRes?.description).toContain('All borrowings finished manually by Admin <span class="font-bold text-foreground">Alice</span> at <span class="font-bold text-foreground">Ruang IFS</span>.');

      const idRes = parseAndLocalizeTimelineNote(note, tId, false);
      expect(idRes).not.toBeNull();
      expect(idRes?.title).toBe('Peminjaman Selesai');
      expect(idRes?.description).toContain('Seluruh peminjaman diselesaikan secara manual oleh Admin <span class="font-bold text-foreground">Alice</span> di <span class="font-bold text-foreground">Ruang IFS</span>.');
    });

    it('localizes finish all assignment note in English and Indonesian', () => {
      const note = 'Seluruh penugasan diselesaikan secara manual oleh Admin Bob di Ruang Server.';

      const enRes = parseAndLocalizeTimelineNote(note, tEn, true);
      expect(enRes).not.toBeNull();
      expect(enRes?.title).toBe('Assignment Completed');
      expect(enRes?.description).toContain('All assignments finished manually by Admin <span class="font-bold text-foreground">Bob</span> at <span class="font-bold text-foreground">Ruang Server</span>.');

      const idRes = parseAndLocalizeTimelineNote(note, tId, true);
      expect(idRes).not.toBeNull();
      expect(idRes?.title).toBe('Penugasan Selesai');
      expect(idRes?.description).toContain('Seluruh penugasan diselesaikan secara manual oleh Admin <span class="font-bold text-foreground">Bob</span> di <span class="font-bold text-foreground">Ruang Server</span>.');
    });

    it('handles legacy finish all note format with status description suffix', () => {
      const note = 'Seluruh peminjaman diselesaikan secara manual oleh Admin di Gudang Utama. Seluruh aset berstatus Tersedia.';

      const enRes = parseAndLocalizeTimelineNote(note, tEn, false);
      expect(enRes).not.toBeNull();
      expect(enRes?.title).toBe('Borrowing Completed');
      expect(enRes?.description).toContain('All borrowings finished manually by Admin <span class="font-bold text-foreground">Admin</span> at <span class="font-bold text-foreground">Gudang Utama</span>.');
    });
  });

  describe('Finish Specific Asset Manual Borrowing / Assignment', () => {
    it('localizes single asset borrowing completion in English and Indonesian', () => {
      const note = 'Peminjaman aset AST-FIN-1 diselesaikan oleh Admin Alice di Ruang Server.';

      const enRes = parseAndLocalizeTimelineNote(note, tEn, false);
      expect(enRes).not.toBeNull();
      expect(enRes?.title).toBe('Asset Return');
      expect(enRes?.description).toContain('Borrowing of asset <span class="font-bold text-foreground">AST-FIN-1</span> finished by Admin <span class="font-bold text-foreground">Alice</span> at <span class="font-bold text-foreground">Ruang Server</span>.');

      const idRes = parseAndLocalizeTimelineNote(note, tId, false);
      expect(idRes).not.toBeNull();
      expect(idRes?.title).toBe('Pengembalian Aset');
      expect(idRes?.description).toContain('Peminjaman aset <span class="font-bold text-foreground">AST-FIN-1</span> diselesaikan oleh Admin <span class="font-bold text-foreground">Alice</span> di <span class="font-bold text-foreground">Ruang Server</span>.');
    });

    it('localizes single asset assignment completion in English and Indonesian', () => {
      const note = 'Penugasan aset AST-LT-99 diselesaikan oleh Admin Bob di Ruang IT.';

      const enRes = parseAndLocalizeTimelineNote(note, tEn, true);
      expect(enRes).not.toBeNull();
      expect(enRes?.title).toBe('Asset Return');
      expect(enRes?.description).toContain('Assignment of asset <span class="font-bold text-foreground">AST-LT-99</span> finished by Admin <span class="font-bold text-foreground">Bob</span> at <span class="font-bold text-foreground">Ruang IT</span>.');

      const idRes = parseAndLocalizeTimelineNote(note, tId, true);
      expect(idRes).not.toBeNull();
      expect(idRes?.title).toBe('Pengembalian Aset');
      expect(idRes?.description).toContain('Penugasan aset <span class="font-bold text-foreground">AST-LT-99</span> diselesaikan oleh Admin <span class="font-bold text-foreground">Bob</span> di <span class="font-bold text-foreground">Ruang IT</span>.');
    });

    it('handles legacy single asset note format with status description suffix', () => {
      const note = 'Peminjaman aset AST-FIN-3 diselesaikan oleh Admin di Ruang IT. Seluruh aset telah berstatus Tersedia.';

      const enRes = parseAndLocalizeTimelineNote(note, tEn, false);
      expect(enRes).not.toBeNull();
      expect(enRes?.title).toBe('Asset Return');
      expect(enRes?.description).toContain('Borrowing of asset <span class="font-bold text-foreground">AST-FIN-3</span> finished by Admin <span class="font-bold text-foreground">Admin</span> at <span class="font-bold text-foreground">Ruang IT</span>.');
    });
  });

  describe('Static Manual Request Confirmation & Returns', () => {
    it('localizes static initial manual confirmation logs', () => {
      expect(parseAndLocalizeTimelineNote('Peminjaman manual (ST) dikonfirmasi oleh Admin', tEn)?.description)
        .toBe('Manual short-term borrowing confirmed by Admin.');
      expect(parseAndLocalizeTimelineNote('Peminjaman (sewa) manual dikonfirmasi oleh Admin', tEn)?.description)
        .toBe('Manual rental borrowing confirmed by Admin.');
      expect(parseAndLocalizeTimelineNote('Assignment manual (LT) dikonfirmasi oleh Admin', tEn)?.description)
        .toBe('Manual long-term assignment confirmed by Admin.');
      expect(parseAndLocalizeTimelineNote('Permintaan manual habis pakai dikonfirmasi oleh Admin', tEn)?.description)
        .toBe('Manual consumable request confirmed by Admin.');
    });

    it('localizes return confirmation note', () => {
      const enRes = parseAndLocalizeTimelineNote('Pengembalian aset dikonfirmasi oleh Admin. Aset telah kembali di gudang.', tEn);
      expect(enRes?.title).toBe('Return Completed');
      expect(enRes?.description).toBe('Asset return confirmed by Admin. Assets have returned to storage.');
    });

    it('returns null for unrelated log notes', () => {
      expect(parseAndLocalizeTimelineNote(null, tEn)).toBeNull();
      expect(parseAndLocalizeTimelineNote('', tEn)).toBeNull();
      expect(parseAndLocalizeTimelineNote('Alokasi barang disetujui', tEn)).toBeNull();
    });
  });
});
