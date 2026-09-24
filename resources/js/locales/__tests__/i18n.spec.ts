import { describe, it, expect, beforeEach } from 'vitest';
import { i18n, setI18nLanguage } from '../index';
import id from '../id';
import en from '../en';

describe('i18n Configuration and Dictionaries', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  it('should initialize with default locale id', () => {
    expect((i18n.global.locale as any).value).toBe('id');
  });

  it('should update active locale when setI18nLanguage is called', () => {
    setI18nLanguage('en');
    expect((i18n.global.locale as any).value).toBe('en');
    expect(document.querySelector('html')?.getAttribute('lang')).toBe('en');
  });

  it('should translate common keys correctly in both languages', () => {
    setI18nLanguage('id');
    expect(i18n.global.t('common.save')).toBe('Simpan');
    expect(i18n.global.t('common.cancel')).toBe('Batal');

    setI18nLanguage('en');
    expect(i18n.global.t('common.save')).toBe('Save');
    expect(i18n.global.t('common.cancel')).toBe('Cancel');
  });

  it('should support parameter interpolation', () => {
    setI18nLanguage('id');
    expect(
      i18n.global.t('common.notifications.newBadge', { count: 5 })
    ).toBe('5 baru');

    setI18nLanguage('en');
    expect(
      i18n.global.t('common.notifications.newBadge', { count: 5 })
    ).toBe('5 new');
  });

  it('should have parity between Indonesian and English dictionaries', () => {
    function getKeys(obj: Record<string, any>, prefix = ''): string[] {
      let keys: string[] = [];
      for (const [k, v] of Object.entries(obj)) {
        const fullKey = prefix ? `${prefix}.${k}` : k;
        if (typeof v === 'object' && v !== null) {
          keys = keys.concat(getKeys(v, fullKey));
        } else {
          keys.push(fullKey);
        }
      }
      return keys;
    }

    const idKeys = getKeys(id).sort();
    const enKeys = getKeys(en).sort();

    expect(idKeys).toEqual(enKeys);
  });

  it('should translate navigation items correctly in both languages', () => {
    setI18nLanguage('id');
    expect(i18n.global.t('nav.items.dashboard')).toBe('Dashboard');
    expect(i18n.global.t('nav.items.needApproval')).toBe('Perlu Approval');
    expect(i18n.global.t('nav.items.processed')).toBe('Sudah Diproses');

    setI18nLanguage('en');
    expect(i18n.global.t('nav.items.dashboard')).toBe('Dashboard');
    expect(i18n.global.t('nav.items.needApproval')).toBe('Pending Approval');
    expect(i18n.global.t('nav.items.processed')).toBe('Processed');
    expect(i18n.global.t('nav.items.inventoryManagement')).toBe('Item Management');
    expect(i18n.global.t('inventory.inventoryManagement')).toBe('Item Management');
    expect(i18n.global.t('nav.items.consumableStock')).toBe('Consumables Stock List');
    expect(i18n.global.t('inventory.consumableStockList')).toBe('Consumables Stock List');
    expect(i18n.global.t('nav.items.pendingInactive')).toBe('Pending Deactivation List');
    expect(i18n.global.t('nav.sections.approvalDeletion')).toBe('DEACTIVATION APPROVAL');
  });

  it('should localize status pending taskbar title distinctly from page heading', () => {
    setI18nLanguage('en');
    expect(i18n.global.t('approvals.statusPendingTaskbarTitle')).toBe('Status Approval: Pending');
    expect(i18n.global.t('approvals.statusPendingTitle')).toBe('Status Approval: Requires Your Attention');

    setI18nLanguage('id');
    expect(i18n.global.t('approvals.statusPendingTaskbarTitle')).toBe('Approval Status: Pending');
    expect(i18n.global.t('approvals.statusPendingTitle')).toBe('Approval Status: Perlu Perhatian Anda');
  });

  it('should localize auditActionTypes properly in both languages', () => {
    setI18nLanguage('en');
    expect(i18n.global.t('admin.auditActionTypes.registrasi')).toBe('Registration');
    expect(i18n.global.t('admin.auditActionTypes.perubahanStatus')).toBe('Status Change');
    expect(i18n.global.t('admin.auditActionTypes.perubahanKondisi')).toBe('Condition Change');
    expect(i18n.global.t('admin.auditActionTypes.pemindahan')).toBe('Relocation');
    expect(i18n.global.t('admin.auditActionTypes.peminjaman')).toBe('Borrowing');
    expect(i18n.global.t('admin.auditActionTypes.pengembalian')).toBe('Return');

    setI18nLanguage('id');
    expect(i18n.global.t('admin.auditActionTypes.registrasi')).toBe('Registrasi');
    expect(i18n.global.t('admin.auditActionTypes.perubahanStatus')).toBe('Perubahan Status');
    expect(i18n.global.t('admin.auditActionTypes.perubahanKondisi')).toBe('Perubahan Kondisi');
    expect(i18n.global.t('admin.auditActionTypes.pemindahan')).toBe('Pemindahan');
    expect(i18n.global.t('admin.auditActionTypes.peminjaman')).toBe('Peminjaman');
    expect(i18n.global.t('admin.auditActionTypes.pengembalian')).toBe('Pengembalian');
  });

  it('should translate access management items correctly in both languages', () => {
    setI18nLanguage('en');
    expect(i18n.global.t('access.title')).toBe('Access Management');
    expect(i18n.global.t('access.tabs.users')).toBe('User Management');
    expect(i18n.global.t('access.tabs.roles')).toBe('Role Management');
    expect(i18n.global.t('access.roles.names.superadmin')).toBe('Super Administrator');
    expect(i18n.global.t('access.roles.groups.dashboard')).toBe('Dashboard');
    expect(i18n.global.t('access.roles.permissions.dashboard.admin.view')).toBe('View Admin Dashboard');

    setI18nLanguage('id');
    expect(i18n.global.t('access.title')).toBe('Manajemen Akses');
    expect(i18n.global.t('access.tabs.users')).toBe('Manajemen Pengguna');
    expect(i18n.global.t('access.tabs.roles')).toBe('Manajemen Peran');
    expect(i18n.global.t('access.roles.names.superadmin')).toBe('Super Administrator');
    expect(i18n.global.t('access.roles.groups.dashboard')).toBe('Dashboard');
    expect(i18n.global.t('access.roles.permissions.dashboard.admin.view')).toBe('Lihat Dashboard Admin');
  });
});
