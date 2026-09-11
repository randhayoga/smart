import { describe, it, expect, beforeEach } from 'vitest';
import { getLocalizedAuditAction, AUDIT_ACTION_KEY_MAP } from '../auditAction';
import { setI18nLanguage } from '@/locales';

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
    expect(getLocalizedAuditAction('Approval')).toBe('Approval');
    expect(getLocalizedAuditAction('Penghapusan')).toBe('Penghapusan');
    expect(getLocalizedAuditAction('Deaktivasi')).toBe('Deaktivasi');
    expect(getLocalizedAuditAction('Adjustment')).toBe('Penyesuaian');
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
    expect(getLocalizedAuditAction('Penghapusan')).toBe('Disposal');
    expect(getLocalizedAuditAction('Deaktivasi')).toBe('Deactivation');
    expect(getLocalizedAuditAction('Adjustment')).toBe('Adjustment');
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
});
