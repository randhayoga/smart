import { markRaw } from 'vue';
import { parseAssetStatuses } from '@/lib/assetStatus';

export interface CompactLotEntry {
  id: number;
  number: string;
  po_number: string;
  organizer_id: number | null;
  organizer: string;
  vendor_id: number | null;
  legacy_vendor_id: number | null;
  vendor: string | null;
  legacy_vendor: string | null;
  unit_price: number | null;
  image_url: string | null;
  grant_date: string | null;
  burden: string;
  project_id: number | null;
  barang_id: number | null;
  barang_code: string;
  barang_nama: string;
  barang_brand: string;
  barang_specification: string;
  barang_category: string;
  barang_category_code: string | null;
  barang_subcategory: string;
  barang_uom: string;
  is_comp: boolean;
  is_sw: boolean;
}

export interface CompactLocationEntry {
  building_id: number | null;
  full_name: string;
  floor_id: number | null;
  floor_name: string | null;
  room_id: number | null;
  room_name: string | null;
}

export interface CompactDeletionApprovalEntry {
  pc?: string;
  mu?: string;
  lu?: string;
  ba?: string;
  bd?: string;
  an?: string;
  dn?: string;
}

export interface CompactUnitSparse {
  as?: string;
  bt?: string;
  cl?: string;
  cs?: string;
  pr?: number;
  im?: string;
  vr?: string;
  al?: number;
  le?: string;
  rr?: number;
  bu?: string;
  pi?: number;
  lv?: number;
  qp?: boolean;
  lq?: string;
}

export type CompactUnitTuple = [
  number,           // 0: id
  string,           // 1: number
  string | 0 | null,// 2: legacy_number (0 if identical to number)
  number,           // 3: lot_id
  number,           // 4: location_id
  number,           // 5: status index in statuses[]
  number,           // 6: condition index in conditions[]
  number,           // 7: updated_at index in uats[]
  CompactUnitSparse?// 8: optional sparse overrides
];

export interface CompactUnitsPayload {
  lots: Record<number, CompactLotEntry>;
  locs: Record<number, string | CompactLocationEntry>;
  statuses: string[];
  conditions: string[];
  uats: string[];
  vendors?: Record<number, string>;
  projects?: Record<number, string>;
  da?: Record<number, CompactDeletionApprovalEntry>;
  rows: CompactUnitTuple[];
}

export interface UnitsFacets {
  categories: string[];
  allSubcategories: string[];
  subByCat: Map<string, Set<string>>;
  brands: string[];
  hasComp: boolean;
  standardStatuses: string[];
}

export interface PreparedUnitsBundle {
  units: any[];
  standardUnits: any[];
  facets: UnitsFacets;
}

function indexUnitAndAccumulateFacets(
  u: any,
  catSet: Set<string>,
  allSubSet: Set<string>,
  subByCat: Map<string, Set<string>>,
  brandSet: Set<string>,
  standardStatusSet: Set<string>,
  standardUnits: any[]
): boolean {
  const tokens = parseAssetStatuses(u.status, u.approval_status);
  u._statusTokens = tokens;

  let isStandard = true;
  if (tokens.length > 0) {
    let hasNonPending = false;
    for (let i = 0; i < tokens.length; i++) {
      const lower = tokens[i].toLowerCase();
      if (lower === 'belum diverifikasi') {
        isStandard = false;
        break;
      }
      if (!lower.startsWith('pending')) {
        hasNonPending = true;
      }
    }
    if (isStandard && !hasNonPending) {
      isStandard = false;
    }
  }
  u._isStandardScope = isStandard;

  if (isStandard) {
    standardUnits.push(u);
    for (let i = 0; i < tokens.length; i++) {
      if (tokens[i]) standardStatusSet.add(tokens[i]);
    }
  }

  const numStr = u.number || '';
  const namaStr = u.barang_nama || '';
  const legStr = u.legacy_number || '';
  u._searchText = `${numStr}\0${namaStr}\0${legStr}`.toLowerCase();

  if (u.barang_category) {
    catSet.add(u.barang_category);
    if (u.barang_subcategory) {
      let subSet = subByCat.get(u.barang_category);
      if (!subSet) {
        subSet = new Set<string>();
        subByCat.set(u.barang_category, subSet);
      }
      subSet.add(u.barang_subcategory);
    }
  }
  if (u.barang_subcategory) {
    allSubSet.add(u.barang_subcategory);
  }
  if (u.barang_brand) {
    brandSet.add(u.barang_brand);
  }

  const cLower = (u.barang_category_code || u.barang_category || '').toLowerCase();
  return cLower === 'comp' || cLower === 'computer';
}

/**
 * Hydrates compact tuple payloads (or indexes raw unit arrays) in a single pass
 * and wraps each unit object in Vue's `markRaw()` to prevent 300,000+ deep reactive proxy allocations.
 */
export function prepareUnitsBundle(
  unitsProp?: any[],
  compactProp?: CompactUnitsPayload
): PreparedUnitsBundle {
  const catSet = new Set<string>();
  const allSubSet = new Set<string>();
  const subByCat = new Map<string, Set<string>>();
  const brandSet = new Set<string>();
  const standardStatusSet = new Set<string>();
  const standardUnits: any[] = [];
  let hasComp = false;

  if (compactProp && Array.isArray(compactProp.rows)) {
    const {
      lots = {},
      locs = {},
      statuses = [],
      conditions = [],
      uats = [],
      vendors = {},
      projects = {},
      da = {},
      rows,
    } = compactProp;

    const len = rows.length;
    const units = new Array(len);

    for (let i = 0; i < len; i++) {
      const tuple = rows[i];
      const id = tuple[0];
      const number = tuple[1];
      const legacyRaw = tuple[2];
      const legacyNumber = legacyRaw === 0 ? number : (legacyRaw || null);
      const lotId = tuple[3];
      const locId = tuple[4];
      const status = statuses[tuple[5]] || '';
      const condition = conditions[tuple[6]] || '';
      const updatedAt = uats[tuple[7]] || '-';
      const sparse = tuple[8];

      const lotInfo = lotId ? lots[lotId] : undefined;
      const locEntry = locId ? locs[locId] : undefined;
      const locFullName = typeof locEntry === 'string' ? locEntry : locEntry?.full_name;
      const daEntry = da[id];

      const isCompCat = lotInfo ? lotInfo.is_comp : false;
      const isSwCat = lotInfo ? lotInfo.is_sw : false;
      const modelSpec = lotInfo ? lotInfo.barang_specification : null;
      const specValue = isCompCat ? (sparse?.cs ?? null) : modelSpec;

      const assetLegacyVendorId = sparse?.lv ?? null;
      const assetLegacyVendorName = assetLegacyVendorId ? (vendors[assetLegacyVendorId] ?? null) : null;
      const effectiveVendorId = lotInfo?.vendor_id ?? assetLegacyVendorId ?? lotInfo?.legacy_vendor_id ?? null;
      const effectiveLegacyVendorId = assetLegacyVendorId ?? lotInfo?.legacy_vendor_id ?? null;
      const effectiveVendorName =
        lotInfo?.vendor ||
        assetLegacyVendorName ||
        lotInfo?.legacy_vendor ||
        '-';

      const borrowingType = sparse?.bt || 'LT';
      const projectId = sparse?.pi ?? lotInfo?.project_id ?? null;
      const projectNo = projectId ? (projects[projectId] ?? null) : null;

      const unit: any = {
        id,
        number,
        legacy_number: legacyNumber,
        status,
        approval_status: sparse?.as ?? null,
        condition,
        type: borrowingType,
        borrowing_type: borrowingType,
        classification: sparse?.cl || 'Aset',
        comp_specification: specValue,
        specification: specValue,
        location: locFullName || '-',
        location_id: locId || null,
        price: sparse?.pr ?? lotInfo?.unit_price ?? 0,
        image_url: sparse?.im || lotInfo?.image_url || 'inventory/lots/placeholder.jpg',
        is_assigned_to_location: Boolean(sparse?.al),
        is_software: isSwCat,
        burden: sparse?.bu || lotInfo?.burden || 'Corporate',
        vendor_id: effectiveVendorId,
        vendor: effectiveVendorName,
        lot_vendor: effectiveVendorName,
        organizer_id: lotInfo?.organizer_id ?? null,
        organizer: lotInfo?.organizer || '-',
        lot_organizer: lotInfo?.organizer || '-',
        updated_at: updatedAt,
        barang_id: lotInfo?.barang_id ?? null,
        barang_code: lotInfo?.barang_code || '-',
        barang_brand: lotInfo?.barang_brand || '-',
        barang_nama: lotInfo?.barang_nama || '-',
        barang_specification: modelSpec || '-',
        barang_category: lotInfo?.barang_category || '-',
        barang_category_code: lotInfo?.barang_category_code ?? null,
        barang_subcategory: lotInfo?.barang_subcategory || '-',
        barang_uom: lotInfo?.barang_uom || '-',
        lot_id: lotId || null,
        lot_number: lotInfo?.number || '-',
        lot_po_number: lotInfo?.po_number || '-',
        qc_pass: sparse?.qp ?? null,
        last_qc: sparse?.lq ?? null,
      };

      if (daEntry) {
        if (daEntry.pc) {
          unit.proposed_status = daEntry.pc;
          unit.proposed_condition = daEntry.pc;
        }
        if (daEntry.mu) unit.memo_url = daEntry.mu;
        if (daEntry.lu) unit.lost_doc_url = daEntry.lu;
        if (daEntry.ba) unit.bod_boc_approval_url = daEntry.ba;
        if (daEntry.bd) unit.bod_boc_disapproval_url = daEntry.bd;
        if (daEntry.an) unit.admin_note = daEntry.an;
        if (daEntry.dn) unit.dm_note = daEntry.dn;
      }

      if (sparse) {
        if (sparse.vr) unit.vehicle_registration = sparse.vr;
        if (sparse.le) unit.license_exp_date = sparse.le;
        if (sparse.rr !== undefined) unit.renewal_reminder = sparse.rr;
      }

      if (projectId) {
        unit.project_id = projectId;
        if (projectNo) unit.project_no = projectNo;
      }
      if (assetLegacyVendorName) unit.asset_vendor = assetLegacyVendorName;
      if (assetLegacyVendorId) unit.asset_vendor_id = assetLegacyVendorId;
      if (effectiveLegacyVendorId) unit.legacy_vendor_id = effectiveLegacyVendorId;
      if (lotInfo?.vendor_id) unit.lot_vendor_id = lotInfo.vendor_id;
      if (lotInfo?.image_url) unit.lot_imageUrl = lotInfo.image_url;
      if (lotInfo?.unit_price !== null && lotInfo?.unit_price !== undefined) {
        unit.lot_unitPrice = lotInfo.unit_price;
      }
      if (lotInfo?.grant_date) {
        unit.lot_purchase_grant_date = lotInfo.grant_date;
        unit.lot_date_of_receipt = lotInfo.grant_date;
      }

      if (
        indexUnitAndAccumulateFacets(
          unit,
          catSet,
          allSubSet,
          subByCat,
          brandSet,
          standardStatusSet,
          standardUnits
        )
      ) {
        hasComp = true;
      }

      units[i] = markRaw(unit);
    }

    return {
      units,
      standardUnits,
      facets: {
        categories: Array.from(catSet).sort(),
        allSubcategories: Array.from(allSubSet).sort(),
        subByCat,
        brands: Array.from(brandSet).sort(),
        hasComp,
        standardStatuses: Array.from(standardStatusSet),
      },
    };
  }

  const rawUnits = unitsProp || [];
  const len = rawUnits.length;
  const units = new Array(len);

  for (let i = 0; i < len; i++) {
    const u = rawUnits[i];
    if (
      indexUnitAndAccumulateFacets(
        u,
        catSet,
        allSubSet,
        subByCat,
        brandSet,
        standardStatusSet,
        standardUnits
      )
    ) {
      hasComp = true;
    }
    units[i] = markRaw(u);
  }

  return {
    units,
    standardUnits,
    facets: {
      categories: Array.from(catSet).sort(),
      allSubcategories: Array.from(allSubSet).sort(),
      subByCat,
      brands: Array.from(brandSet).sort(),
      hasComp,
      standardStatuses: Array.from(standardStatusSet),
    },
  };
}
