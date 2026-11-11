import { describe, it, expect } from 'vitest';
import { formatRupiah, formatDate, formatDateTime, formatLocation } from '../utils';

describe('utils.ts formatRupiah', () => {
  it('formats positive integers correctly', () => {
    expect(formatRupiah(5000000)).toBe('Rp5.000.000');
    expect(formatRupiah(15000000)).toBe('Rp15.000.000');
    expect(formatRupiah(5000)).toBe('Rp5.000');
    expect(formatRupiah(0)).toBe('Rp0');
  });

  it('formats raw numeric strings from databases correctly', () => {
    expect(formatRupiah('5000000')).toBe('Rp5.000.000');
    expect(formatRupiah('5000000.00')).toBe('Rp5.000.000');
    expect(formatRupiah('1250000.50')).toBe('Rp1.250.000');
  });

  it('handles Indonesian dot-separated formatted strings without omitting zeros', () => {
    expect(formatRupiah('5.000.000')).toBe('Rp5.000.000');
    expect(formatRupiah('5.000.000,00')).toBe('Rp5.000.000');
    expect(formatRupiah('15.500.000')).toBe('Rp15.500.000');
  });

  it('handles strings with currency prefix and whitespace', () => {
    expect(formatRupiah('Rp 5.000.000')).toBe('Rp5.000.000');
    expect(formatRupiah('Rp. 5.000.000')).toBe('Rp5.000.000');
    expect(formatRupiah('Rp5000000')).toBe('Rp5.000.000');
    expect(formatRupiah('IDR 5000000')).toBe('Rp5.000.000');
  });

  it('handles English comma-separated formatted strings', () => {
    expect(formatRupiah('5,000,000')).toBe('Rp5.000.000');
    expect(formatRupiah('5,000,000.00')).toBe('Rp5.000.000');
  });

  it('returns "-" for null, undefined, empty, or invalid inputs', () => {
    expect(formatRupiah(null)).toBe('-');
    expect(formatRupiah(undefined)).toBe('-');
    expect(formatRupiah('')).toBe('-');
    expect(formatRupiah('-')).toBe('-');
    expect(formatRupiah('invalid')).toBe('-');
  });
});
