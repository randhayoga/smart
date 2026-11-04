import { describe, it, expect } from 'vitest';
import { isReactive } from 'vue';
import {
  prepareUnitsBundle,
  type CompactUnitsPayload,
} from '../hydrateCompactUnits';
import { formatLocation } from '@/lib/utils';

describe('hydrateCompactUnits / prepareUnitsBundle & formatLocation', () => {
  const sampleCompact: CompactUnitsPayload = {
    lots: {
      10: {
        id: 10,
        number: 'LOT-2026-COMP-001',
        po_number: 'PO-999',
        organizer_id: 2,
        organizer: 'IFS',
        vendor_id: 5,
        legacy_vendor_id: null,
        vendor: 'PT Lenovo Indonesia',
        legacy_vendor: null,
        unit_price: 25000000,
        image_url: 'inventory/lots/thinkpad.jpg',
        grant_date: '2026-01-15',
        burden: 'Corporate',
        project_id: null,
        barang_id: 77,
        barang_code: 'COMP-LP-0001',
        barang_nama: 'ThinkPad X1 Carbon',
        barang_brand: 'ThinkPad',
        barang_specification: 'Core i7, 32GB RAM',
        barang_category: 'Computer',
        barang_category_code: 'COMP',
        barang_subcategory: 'Laptop',
        barang_uom: 'Unit',
        is_comp: true,
        is_sw: false,
      },
    },
    locs: {
      30: 'HQ Building, Floor 3, IT Room',
    },
    statuses: ['Tersedia', 'Tidak Aktif,Pending:BoD/BoC'],
    conditions: ['Bagus', 'Rusak Total'],
    uats: ['04-11-2026 10:30'],
    vendors: {
      8: 'PT Legacy Vendor',
    },
    projects: {
      100: 'PRJ-OPT-100',
    },
    da: {
      502: {
        pc: 'Rusak Total',
        mu: 'memos/memo-502.pdf',
        an: 'Perlu penghapusan',
      },
    },
    rows: [
      // Row 1: [id, number, legacy_diff, lot_id, loc_id, status_idx, cond_idx, uat_idx, sparse]
      [
        501,
        '00001-COMP-LP-IFS-PTRE-26',
        'LEG-01',
        10,
        30,
        0,
        0,
        0,
        { cs: 'Custom RAM 32GB', pr: 26000000, bu: 'Project', pi: 100 },
      ],
      // Row 2: legacy same as number (0), deletion approval attached via da[502], legacy vendor override
      [
        502,
        '00002-COMP-LP-IFS-PTRE-26',
        0,
        10,
        30,
        1,
        1,
        0,
        { as: 'Pending:BoD/BoC', lv: 8 },
      ],
    ],
  };

  it('reconstructs complete unit objects with preserved leaf location_id and non-duplicated comma-separated location', () => {
    const bundle = prepareUnitsBundle(undefined, sampleCompact);

    expect(bundle.units).toHaveLength(2);
    const u1 = bundle.units[0];
    expect(u1.id).toBe(501);
    expect(u1.number).toBe('00001-COMP-LP-IFS-PTRE-26');
    expect(u1.legacy_number).toBe('LEG-01');
    expect(u1.barang_nama).toBe('ThinkPad X1 Carbon');
    expect(u1.barang_brand).toBe('ThinkPad');
    expect(u1.barang_category).toBe('Computer');
    expect(u1.barang_category_code).toBe('COMP');
    expect(u1.barang_subcategory).toBe('Laptop');
    expect(u1.barang_uom).toBe('Unit');
    expect(u1.comp_specification).toBe('Custom RAM 32GB');
    expect(u1.specification).toBe('Custom RAM 32GB');
    expect(u1.location).toBe('HQ Building, Floor 3, IT Room');
    expect(u1.location_id).toBe(30);
    expect(formatLocation(u1.location, u1.floor, u1.room)).toBe('HQ Building, Floor 3, IT Room');
    expect(u1.price).toBe(26000000);
    expect(u1.burden).toBe('Project');
    expect(u1.project_id).toBe(100);
    expect(u1.project_no).toBe('PRJ-OPT-100');
    expect(u1.lot_organizer).toBe('IFS');
    expect(u1.lot_vendor).toBe('PT Lenovo Indonesia');
    expect(u1.updated_at).toBe('04-11-2026 10:30');

    const u2 = bundle.units[1];
    expect(u2.id).toBe(502);
    expect(u2.legacy_number).toBe('00002-COMP-LP-IFS-PTRE-26');
    expect(u2.approval_status).toBe('Pending:BoD/BoC');
    expect(u2.proposed_status).toBe('Rusak Total');
    expect(u2.memo_url).toBe('memos/memo-502.pdf');
    expect(u2.admin_note).toBe('Perlu penghapusan');
    expect(u2.asset_vendor).toBe('PT Legacy Vendor');
    expect(u2.legacy_vendor_id).toBe(8);
  });

  it('marks hydrated row objects with markRaw() so Vue does not create deep reactive proxies', () => {
    const bundle = prepareUnitsBundle(undefined, sampleCompact);
    expect(isReactive(bundle.units[0])).toBe(false);
    expect(isReactive(bundle.units[1])).toBe(false);
  });

  it('precomputes single-pass facets, _statusTokens, _isStandardScope, and _searchText', () => {
    const bundle = prepareUnitsBundle(undefined, sampleCompact);

    expect(bundle.facets.categories).toEqual(['Computer']);
    expect(bundle.facets.allSubcategories).toEqual(['Laptop']);
    expect(bundle.facets.brands).toEqual(['ThinkPad']);
    expect(bundle.facets.hasComp).toBe(true);
    expect(bundle.facets.standardStatuses).toContain('Tersedia');
    expect(bundle.facets.standardStatuses).toContain('Pending:BoD/BoC');

    expect(bundle.units[0]._isStandardScope).toBe(true);
    expect(bundle.units[0]._searchText).toContain('thinkpad x1 carbon');
    expect(bundle.units[0]._searchText).toContain('leg-01');
  });

  it('formatLocation standardizes comma separator globally and prevents duplicate floor/room layers', () => {
    expect(
      formatLocation('Graha RE 1, Lantai 3, Departemen Civil', 'Lantai 3', 'Departemen Civil')
    ).toBe('Graha RE 1, Lantai 3, Departemen Civil');

    expect(
      formatLocation('Graha RE 1', 'Lantai Mezzanine', 'Ruang IFS')
    ).toBe('Graha RE 1, Lantai Mezzanine, Ruang IFS');

    expect(
      formatLocation('Graha RE 1 - Lantai Mezzanine - Ruang IFS')
    ).toBe('Graha RE 1, Lantai Mezzanine, Ruang IFS');
  });
});
