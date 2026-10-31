import { describe, it, expect } from 'vitest';
import {
  parseAssetStatuses,
  hasAssetStatus,
  getBaseAssetStatus,
  isRestrictedAssetStatus,
  getAssetStatusBadgeClass,
  getAssetStatusLabel,
  formatAssetStatusLabels,
} from '../assetStatus';

describe('assetStatus utility', () => {
  it('parses single and comma-separated asset statuses', () => {
    expect(parseAssetStatuses(null)).toEqual([]);
    expect(parseAssetStatuses('')).toEqual([]);
    expect(parseAssetStatuses('Tersedia')).toEqual(['Tersedia']);
    expect(parseAssetStatuses('Tersedia,Pending:DM')).toEqual(['Tersedia', 'Pending:DM']);
    expect(parseAssetStatuses(' Tidak Aktif , Pending:BoD/BoC ')).toEqual(['Tidak Aktif', 'Pending:BoD/BoC']);
  });

  it('checks hasAssetStatus case-insensitively across comma-separated tokens', () => {
    expect(hasAssetStatus('Tersedia,Pending:DM', 'Pending:DM')).toBe(true);
    expect(hasAssetStatus('Tersedia,Pending:DM', 'tersedia')).toBe(true);
    expect(hasAssetStatus('Tidak Aktif,Pending:BoD/BoC', 'Pending:BoD/BoC')).toBe(true);
    expect(hasAssetStatus('Tidak Aktif,Pending:BoD/BoC', 'Dihapus')).toBe(false);
  });

  it('extracts base asset status excluding Pending tokens', () => {
    expect(getBaseAssetStatus('Tersedia,Pending:DM')).toBe('Tersedia');
    expect(getBaseAssetStatus('Tidak Aktif,Pending:BoD/BoC')).toBe('Tidak Aktif');
    expect(getBaseAssetStatus('Pending:DM')).toBe('Tersedia');
    expect(getBaseAssetStatus('Dihapus')).toBe('Dihapus');
  });

  it('identifies restricted asset statuses accurately', () => {
    expect(isRestrictedAssetStatus('Tersedia')).toBe(false);
    expect(isRestrictedAssetStatus('Dipinjam (ST)')).toBe(false);
    expect(isRestrictedAssetStatus('Tersedia,Pending:DM')).toBe(true);
    expect(isRestrictedAssetStatus('Tidak Aktif,Pending:BoD/BoC')).toBe(true);
    expect(isRestrictedAssetStatus('Dihapus')).toBe(true);
    expect(isRestrictedAssetStatus('Belum Diverifikasi')).toBe(true);
  });

  it('resolves badge classes and localized labels', () => {
    expect(getAssetStatusBadgeClass('Tersedia')).toContain('bg-emerald-100');
    expect(getAssetStatusBadgeClass('Di-assign (LT)')).toContain('bg-sky-100');
    expect(getAssetStatusBadgeClass('Dipinjam (ST)')).toContain('bg-amber-100');
    expect(getAssetStatusBadgeClass('Dipinjam (sewa)')).toContain('bg-indigo-100');
    expect(getAssetStatusBadgeClass('borrowed (rent)')).toContain('bg-indigo-100');
    expect(getAssetStatusBadgeClass('Pending:DM')).toContain('bg-purple-100');
    expect(getAssetStatusBadgeClass('Tidak Aktif')).toContain('bg-gray-200');
    expect(getAssetStatusBadgeClass('Dihapus')).toContain('bg-rose-100');

    const mockT = (k: string) => ({
      'status.tersedia': 'Available',
      'status.diAssignLt': 'Di-assign (LT)',
      'status.dipinjamSt': 'Borrowed (ST)',
      'status.pendingDm': 'Pending:DM',
      'status.tidakAktif': 'Inactive',
      'status.pendingBodBoc': 'Pending:BoD/BoC',
      'status.dihapus': 'Deleted',
    }[k] || k);

    expect(getAssetStatusLabel('Tersedia', mockT)).toBe('Available');
    expect(getAssetStatusLabel('Di-assign (LT)', mockT)).toBe('Di-assign (LT)');
    expect(getAssetStatusLabel('Dipinjam (ST)', mockT)).toBe('Borrowed (ST)');
    expect(formatAssetStatusLabels('Tidak Aktif,Pending:BoD/BoC', mockT)).toBe('Inactive, Pending:BoD/BoC');
  });
});
