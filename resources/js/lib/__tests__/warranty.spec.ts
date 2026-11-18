import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { isWarrantyActive, getWarrantyColorClass, formatWarrantyDate } from '../warranty';

describe('warranty helpers', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-11-17T10:00:00Z'));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  describe('isWarrantyActive', () => {
    it('returns false for null, undefined, or empty string', () => {
      expect(isWarrantyActive(null)).toBe(false);
      expect(isWarrantyActive(undefined)).toBe(false);
      expect(isWarrantyActive('')).toBe(false);
    });

    it('returns true when warranty_date is today or in the future', () => {
      expect(isWarrantyActive('2026-11-17')).toBe(true);
      expect(isWarrantyActive('2027-01-01')).toBe(true);
    });

    it('returns false when warranty_date is in the past', () => {
      expect(isWarrantyActive('2026-11-16')).toBe(false);
      expect(isWarrantyActive('2025-05-01')).toBe(false);
    });
  });

  describe('getWarrantyColorClass', () => {
    it('returns empty string when warranty_date is null or empty', () => {
      expect(getWarrantyColorClass(null)).toBe('');
      expect(getWarrantyColorClass('')).toBe('');
    });

    it('returns green success class when warranty is active', () => {
      expect(getWarrantyColorClass('2026-11-17')).toContain('text-emerald-600');
      expect(getWarrantyColorClass('2027-06-01')).toContain('text-emerald-600');
    });

    it('returns destructive red class when warranty is inactive', () => {
      expect(getWarrantyColorClass('2026-11-16')).toContain('text-destructive');
      expect(getWarrantyColorClass('2020-01-01')).toContain('text-destructive');
    });
  });

  describe('formatWarrantyDate', () => {
    it('returns "-" for null/empty and DD-MM-YYYY for valid dates', () => {
      expect(formatWarrantyDate(null)).toBe('-');
      expect(formatWarrantyDate('')).toBe('-');
      expect(formatWarrantyDate('2026-11-17')).toBe('17-11-2026');
    });
  });
});
