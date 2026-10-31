import { describe, it, expect } from 'vitest';
import {
  getRequestStatusLabel,
  getRequestStatusBadgeClass,
  getRequestStatusPillClass,
  parseRequestStatuses,
} from '../requestStatus';

describe('requestStatus utility', () => {
  it('normalizes raw statuses to Indonesian display labels', () => {
    expect(getRequestStatusLabel('borrow')).toBe('Dipinjam');
    expect(getRequestStatusLabel('sedang dipinjam')).toBe('Dipinjam');
    expect(getRequestStatusLabel('wait')).toBe('Menunggu approval');
    expect(getRequestStatusLabel('approve')).toBe('Di-approve');
    expect(getRequestStatusLabel('reject')).toBe('Ditolak');
  });

  it('assigns unique orange styling to Dipinjam status', () => {
    const badgeClass = getRequestStatusBadgeClass('Dipinjam');
    expect(badgeClass).toContain('bg-orange-100');
    expect(badgeClass).toContain('text-orange-800');
  });

  it('includes base pill styling in getRequestStatusPillClass', () => {
    const pillClass = getRequestStatusPillClass('borrow');
    expect(pillClass).toContain('inline-flex items-center');
    expect(pillClass).toContain('bg-orange-100');
    expect(pillClass).toContain('text-orange-800');
  });

  it('parses comma-separated status tokens', () => {
    expect(parseRequestStatuses('borrow, wait')).toEqual(['borrow', 'wait']);
    expect(parseRequestStatuses(null)).toEqual([]);
  });
});
