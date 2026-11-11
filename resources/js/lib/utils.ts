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
  if (!url) return '/media/inventory/placeholder.jpg';
  if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('/')) {
    return url;
  }
  return `/media/${url}`;
}

/**
 * Formats location hierarchy strings into the canonical comma-separated path
 * (e.g. "Graha RE 1, Lantai Mezzanine, Ruang IFS") without duplicating floor/room layers.
 */
export function formatLocation(
  loc?: string | null,
  floor?: string | null,
  room?: string | null
): string {
  const raw = typeof loc === 'string' ? loc.trim() : '';
  const base = raw ? raw.replace(/\s+-\s+/g, ', ') : '';
  if (!floor && !room) {
    return base || '-';
  }
  if (
    base &&
    (base.includes(', ') ||
      (floor && base.includes(floor.trim())) ||
      (room && base.includes(room.trim())))
  ) {
    return base;
  }
  const parts: string[] = [];
  if (base && base !== '-') parts.push(base);
  if (floor && floor.trim() && floor.trim() !== '-') parts.push(floor.trim());
  if (room && room.trim() && room.trim() !== '-') parts.push(room.trim());
  return parts.length > 0 ? parts.join(', ') : '-';
}

/**
 * Checks whether a location is active.
 * Searches the locations array by ID, name, or full_name.
 * If found and is_active === false, returns false. Otherwise true.
 */
export function isLocationActive(
  locationIdOrName: number | string | null | undefined,
  locations?: Array<{ id?: number | string; name?: string; full_name?: string; is_active?: boolean; [key: string]: any }> | null
): boolean {
  if (locationIdOrName === null || locationIdOrName === undefined || locationIdOrName === '' || locationIdOrName === '-') {
    return true;
  }
  if (!locations || !Array.isArray(locations) || locations.length === 0) {
    return true;
  }
  const needle = String(locationIdOrName).trim();
  const needleLower = needle.toLowerCase();

  const byId = locations.find(loc => loc.id !== undefined && loc.id !== null && String(loc.id) === needle);
  if (byId) {
    return byId.is_active !== false;
  }

  const byName = locations.find(loc => {
    if (loc.full_name && loc.full_name.trim().toLowerCase() === needleLower) return true;
    if (loc.name && loc.name.trim().toLowerCase() === needleLower) return true;
    return false;
  });
  if (byName) {
    return byName.is_active !== false;
  }

  const byPartial = locations.find(loc => {
    if (loc.name && loc.is_active === false && needleLower.includes(loc.name.trim().toLowerCase())) {
      return true;
    }
    return false;
  });
  if (byPartial) {
    return false;
  }

  return true;
}

/**
 * Formats a number or numeric/currency string into standard Indonesian Rupiah format (e.g. 'Rp5.000.000').
 * Robustly parses numbers, raw database decimals ('5000000.00'), Indonesian formatted strings ('5.000.000'),
 * and strings with currency symbols ('Rp 5.000.000').
 */
export function formatRupiah(val: number | string | null | undefined): string {
  if (val === null || val === undefined || val === '') return '-';

  let num: number;
  if (typeof val === 'number') {
    num = val;
  } else {
    const raw = String(val).trim();
    if (raw === '' || raw === '-') return '-';

    // Strip "Rp", "IDR", spaces
    let clean = raw.replace(/^(?:Rp|IDR)\.?\s*/i, '').trim();

    if (clean.includes('.') && clean.includes(',')) {
      if (clean.indexOf('.') < clean.indexOf(',')) {
        // "5.000.000,50" -> Indonesian: remove dot, replace comma with dot
        clean = clean.replace(/\./g, '').replace(',', '.');
      } else {
        // "5,000,000.50" -> English: remove commas
        clean = clean.replace(/,/g, '');
      }
    } else if (clean.includes('.')) {
      const dotCount = (clean.match(/\./g) || []).length;
      if (dotCount > 1 || /\.\d{3}$/.test(clean)) {
        clean = clean.replace(/\./g, '');
      }
    } else if (clean.includes(',')) {
      const commaCount = (clean.match(/,/g) || []).length;
      if (commaCount > 1 || /,\d{3}$/.test(clean)) {
        clean = clean.replace(/,/g, '');
      } else {
        clean = clean.replace(',', '.');
      }
    }

    num = parseFloat(clean);
  }

  if (isNaN(num)) return '-';
  const formatted = Math.floor(num).toLocaleString('id-ID');
  return `Rp${formatted}`;
}

export * from './requestStatus';


