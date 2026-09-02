import type { ClassValue } from "clsx"
import { clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Formats any date string or Date object to 'DD-MM-YYYY'.
 * Handles 'YYYY-MM-DD', 'DD/MM/YYYY', 'DD-MM-YYYY', and ISO strings.
 */
export function formatDate(val: string | Date | null | undefined): string {
  if (!val || val === '-') return '-';

  if (typeof val === 'string') {
    const trimmed = val.trim();
    // Case 1: Starts with YYYY-MM-DD or YYYY/MM/DD
    if (/^\d{4}[-/]\d{2}[-/]\d{2}/.test(trimmed)) {
      const datePart = trimmed.split(/[T ]/)[0];
      const [y, m, d] = datePart.split(/[-/]/);
      return `${d}-${m}-${y}`;
    }
    // Case 2: Starts with DD/MM/YYYY
    if (/^\d{2}\/\d{2}\/\d{4}/.test(trimmed)) {
      return trimmed.replace(/\//g, '-').split(' ')[0];
    }
    // Case 3: Starts with DD-MM-YYYY
    if (/^\d{2}-\d{2}-\d{4}/.test(trimmed)) {
      return trimmed.split(' ')[0];
    }
  }

  const d = typeof val === 'string' ? new Date(val) : val;
  if (isNaN(d.getTime())) return typeof val === 'string' ? val : '-';

  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  return `${day}-${month}-${year}`;
}

/**
 * Formats any date string or Date object to 'DD-MM-YYYY HH:mm' (or 'DD-MM-YYYY HH:mm:ss').
 */
export function formatDateTime(val: string | Date | null | undefined, includeSeconds = false): string {
  if (!val || val === '-') return '-';

  if (typeof val === 'string') {
    const trimmed = val.trim();
    // If already in DD-MM-YYYY HH:mm(:ss) format
    if (/^\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}/.test(trimmed)) {
      if (!includeSeconds) {
        return trimmed.slice(0, 16);
      }
      return trimmed;
    }
    // If DD/MM/YYYY HH:mm(:ss), replace slashes
    if (/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}/.test(trimmed)) {
      const dashed = trimmed.replace(/\//g, '-');
      return !includeSeconds ? dashed.slice(0, 16) : dashed;
    }
  }

  const d = typeof val === 'string' ? new Date(val) : val;
  if (isNaN(d.getTime())) {
    if (typeof val === 'string') return val.replace(/\//g, '-');
    return '-';
  }

  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');

  if (includeSeconds) {
    const seconds = String(d.getSeconds()).padStart(2, '0');
    return `${day}-${month}-${year} ${hours}:${minutes}:${seconds}`;
  }

  return `${day}-${month}-${year} ${hours}:${minutes}`;
}

/**
 * Formats a consistent display name for inventory items or line items in carts and catalogs.
 */
export function formatItemDisplayName(item: {
  barang_id?: number | null;
  name?: string | null;
  subcategory_name?: string | null;
  subcategory?: string | null;
  brand?: string | null;
  spec?: string | null;
  specification?: string | null;
  number?: string | null;
}): string {
  // If explicitly null, this is an unspecific subcategory request (used in cart/confirmation)
  if (item.barang_id === null) {
    return item.subcategory_name || item.subcategory || item.name || 'Barang';
  }

  const parts: string[] = [];
  if (item.brand && item.brand !== '-') {
    parts.push(item.brand);
  }

  const itemName = item.name && item.name !== 'Tidak Spesifik' ? item.name : '';
  if (itemName) {
    parts.push(itemName);
  }

  const spec = item.spec || item.specification;
  if (spec && spec !== '-') {
    parts.push(spec);
  }

  if (parts.length === 0) {
    return item.number || item.subcategory_name || item.subcategory || item.name || 'Barang';
  }

  return parts.join(' ');
}

/**
 * Returns a normalized, safe image URL with fallback to placeholder.
 */
export function formatImageUrl(url?: string | null): string {
  if (!url) return 'https://placehold.co/400x400?text=Barang';
  if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('/')) {
    return url;
  }
  return `/media/${url}`;
}

