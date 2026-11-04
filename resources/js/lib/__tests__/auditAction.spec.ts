import { describe, it, expect, beforeEach } from 'vitest';
import { getLocalizedAuditAction, AUDIT_ACTION_KEY_MAP, renderAuditNote } from '../auditAction';
import { setI18nLanguage } from '@/locales';
import { isVNode } from 'vue';

describe('auditAction utility', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  it('translates all core audit actions to Indonesian when active locale is id', () => {
    setI18nLanguage('id');
    expect(getLocalizedAuditAction('Registrasi')).toBe('Registrasi');
    expect(getLocalizedAuditAction('Perubahan status')).toBe('Perubahan Status');
    expect(getLocalizedAuditAction('Perubahan kondisi')).toBe('Perubahan Kondisi');
    expect(getLocalizedAuditAction('Pemindahan')).toBe('Pemindahan');
    expect(getLocalizedAuditAction('Peminjaman')).toBe('Peminjaman');
    expect(getLocalizedAuditAction('Pengembalian')).toBe('Pengembalian');
    expect(getLocalizedAuditAction('Pemeliharaan')).toBe('Pemeliharaan');
    expect(getLocalizedAuditAction('Approval')).toBe('Persetujuan');
    expect(getLocalizedAuditAction('Persetujuan')).toBe('Persetujuan');
    expect(getLocalizedAuditAction('Penghapusan')).toBe('Penghapusan');
    expect(getLocalizedAuditAction('Deaktivasi')).toBe('Penghapusan');
    expect(getLocalizedAuditAction('Adjustment')).toBe('Penyesuaian');
    expect(getLocalizedAuditAction('Assignment')).toBe('Assignment');
    expect(getLocalizedAuditAction('Disapproval')).toBe('Penolakan');
    expect(getLocalizedAuditAction('Penolakan')).toBe('Penolakan');
    expect(getLocalizedAuditAction('Quality Control')).toBe('Quality Control');
    expect(getLocalizedAuditAction('Pemeriksaan QC')).toBe('Quality Control');
    expect(getLocalizedAuditAction('Perubahan QC')).toBe('Quality Control');
  });

  it('translates all core audit actions to English when active locale is en', () => {
    setI18nLanguage('en');
    expect(getLocalizedAuditAction('Registrasi')).toBe('Registration');
    expect(getLocalizedAuditAction('Perubahan status')).toBe('Status Change');
    expect(getLocalizedAuditAction('Perubahan kondisi')).toBe('Condition Change');
    expect(getLocalizedAuditAction('Pemindahan')).toBe('Relocation');
    expect(getLocalizedAuditAction('Peminjaman')).toBe('Borrowing');
    expect(getLocalizedAuditAction('Pengembalian')).toBe('Return');
    expect(getLocalizedAuditAction('Pemeliharaan')).toBe('Maintenance');
    expect(getLocalizedAuditAction('Approval')).toBe('Approval');
    expect(getLocalizedAuditAction('Persetujuan')).toBe('Approval');
    expect(getLocalizedAuditAction('Penghapusan')).toBe('Deletion');
    expect(getLocalizedAuditAction('Deaktivasi')).toBe('Deletion');
    expect(getLocalizedAuditAction('Adjustment')).toBe('Adjustment');
    expect(getLocalizedAuditAction('Assignment')).toBe('Assignment');
    expect(getLocalizedAuditAction('Disapproval')).toBe('Disapproval');
    expect(getLocalizedAuditAction('Penolakan')).toBe('Disapproval');
    expect(getLocalizedAuditAction('Quality Control')).toBe('Quality Control');
    expect(getLocalizedAuditAction('Pemeriksaan QC')).toBe('Quality Control');
    expect(getLocalizedAuditAction('Perubahan QC')).toBe('Quality Control');
  });

  it('handles case insensitivity and underscore variations', () => {
    setI18nLanguage('en');
    expect(getLocalizedAuditAction('perubahan_status')).toBe('Status Change');
    expect(getLocalizedAuditAction('perubahan_kondisi')).toBe('Condition Change');
    expect(getLocalizedAuditAction('registrasi')).toBe('Registration');
  });

  it('supports passing custom component-scoped translator function', () => {
    const mockT = (key: string) => `mock:${key}`;
    expect(getLocalizedAuditAction('Registrasi', mockT)).toBe('mock:admin.auditActionTypes.registrasi');
  });

  it('falls back gracefully on unknown, empty or dash values', () => {
    expect(getLocalizedAuditAction(null)).toBe('-');
    expect(getLocalizedAuditAction(undefined)).toBe('-');
    expect(getLocalizedAuditAction('-')).toBe('-');
    expect(getLocalizedAuditAction('UnknownAction123')).toBe('UnknownAction123');
  });

  describe('renderAuditNote', () => {
    it('returns dash for null, undefined or empty notes', () => {
      expect(renderAuditNote(null)).toBe('-');
      expect(renderAuditNote(undefined)).toBe('-');
      expect(renderAuditNote('')).toBe('-');
      expect(renderAuditNote('-')).toBe('-');
    });

    it('returns plain text when no requestId is provided', () => {
      const vnode = renderAuditNote('Peminjaman (ST) 112026-0002 untuk Yoga (265656).');
      expect(isVNode(vnode)).toBe(true);
      expect((vnode as any).children[0]).toBe('Peminjaman (ST) 112026-0002 untuk Yoga (265656).');
    });

    it('renders interactive link when requestId and request number are present in note', () => {
      const vnode = renderAuditNote(
        'Peminjaman (ST) 112026-0002 untuk Yoga (265656). Catatan: Keperluan site.',
        42,
        '112026-0002'
      );
      expect(isVNode(vnode)).toBe(true);
      const children = (vnode as any).children;
      expect(children).toHaveLength(3);
      expect(children[0]).toBe('Peminjaman (ST) ');
      expect(isVNode(children[1])).toBe(true);
      expect(children[1].props.href).toBe('/smart/borrowed/42');
      expect(children[2]).toBe(' untuk Yoga (265656). Catatan: Keperluan site.');
    });

    it('renders list items when note contains pipeline delimiter', () => {
      const vnode = renderAuditNote('Line 1 | Line 2', null);
      expect(isVNode(vnode)).toBe(true);
      expect((vnode as any).type).toBe('ul');
      expect((vnode as any).children).toHaveLength(2);
    });
  });
});
