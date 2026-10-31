import { describe, it, expect } from 'vitest';
import { getRequestTypeBadgeClass, getRequestTypeLabel } from '../requestTypeBadge';

describe('requestTypeBadge utility', () => {
  it('returns appropriate badge classes for request and borrowing types', () => {
    expect(getRequestTypeBadgeClass('consumable')).toContain('bg-rose-100');
    expect(getRequestTypeBadgeClass('request')).toContain('bg-rose-100');
    expect(getRequestTypeBadgeClass('permintaan')).toContain('bg-rose-100');

    expect(getRequestTypeBadgeClass('st')).toContain('bg-amber-100');
    expect(getRequestTypeBadgeClass('Borrowing (ST)')).toContain('bg-amber-100');
    expect(getRequestTypeBadgeClass('Peminjaman (ST)')).toContain('bg-amber-100');

    expect(getRequestTypeBadgeClass('rent')).toContain('bg-indigo-100');
    expect(getRequestTypeBadgeClass('Borrowing (Rent)')).toContain('bg-indigo-100');
    expect(getRequestTypeBadgeClass('Peminjaman (Sewa)')).toContain('bg-indigo-100');

    expect(getRequestTypeBadgeClass('lt')).toContain('bg-sky-100');
    expect(getRequestTypeBadgeClass('Assignment (LT)')).toContain('bg-sky-100');

    expect(getRequestTypeBadgeClass(null)).toContain('bg-gray-100');
    expect(getRequestTypeBadgeClass('unknown')).toContain('bg-gray-100');
  });

  it('resolves localized label using translation function', () => {
    const mockT = (k: string) => {
      const map: Record<string, string> = {
        'fulfillment.request': 'Request',
        'fulfillment.manualRequest.typeSt': 'Borrowing (ST)',
        'fulfillment.manualRequest.typeRent': 'Borrowing (Rent)',
        'fulfillment.manualRequest.typeLt': 'Assignment (LT)',
      };
      return map[k] || k;
    };

    expect(getRequestTypeLabel('consumable', mockT)).toBe('Request');
    expect(getRequestTypeLabel('st', mockT)).toBe('Borrowing (ST)');
    expect(getRequestTypeLabel('rent', mockT)).toBe('Borrowing (Rent)');
    expect(getRequestTypeLabel('lt', mockT)).toBe('Assignment (LT)');
    expect(getRequestTypeLabel(null, mockT)).toBe('-');
  });
});
