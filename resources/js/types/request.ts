import type { RequestStatus, RawRequestStatus } from '@/lib/requestStatus';

export interface SmartRequestItem {
  id: number;
  barang_id?: number | null;
  subcategory: string;
  brand: string;
  name?: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  stock?: number;
  imageUrl?: string | null;
  category: string;
  assets?: string[];
  is_consumable?: boolean;
  uom?: string;
  status?: string;
}

export interface SmartApprovalDetail {
  id?: number;
  approver_name?: string | null;
  decision?: 'approve' | 'reject' | string | null;
  note?: string | null;
  decided_at?: string | null;
}

export interface SmartRequestData {
  id: number;
  uuid?: string;
  number: string;
  type: 'permintaan' | 'peminjaman' | string;
  requester?: string;
  pemanfaatan?: 'corporate' | 'project' | string;
  pemanfaatanDetail?: string;
  destination?: string;
  reasoning?: string;
  durationStart?: string | null;
  durationEnd?: string | null;
  durationDays?: number;
  durationHours?: number;
  borrowPeriod?: string | null;
  status: RequestStatus | string;
  raw_status?: RawRequestStatus | string;
  rawStatus?: RawRequestStatus | string;
  created_at?: string;
  createdAt?: string;
  approver_name?: string | null;
  approval?: SmartApprovalDetail | null;
  approval_by?: string | null;
  approval_at?: string | null;
  confirmation_by?: string | null;
  confirmation_at?: string | null;
  return_confirmed_by?: string | null;
  handover_method?: string | null;
  handover_time?: string | null;
  handover_location?: string | null;
  handover_note?: string | null;
  is_stock_sufficient?: boolean;
  items?: SmartRequestItem[];
  logs?: Array<{
    id: number;
    status_from: string;
    status_to: string;
    time: string;
    actor: string;
    user: string;
    note: string;
  }>;
  lifecycles?: Array<{
    waktu: string;
    status: string;
    aktor: string;
    durasi: string | number;
    catatan: string;
  }>;
}

export interface RequestModalInfoField {
  label: string;
  value: string;
  isBadge?: boolean;
  isSufficient?: boolean;
}

/** Formats request summary fields for approval and confirmation modal inspection */
export function formatRequestModalFields(req: SmartRequestData): RequestModalInfoField[] {
  const fields: RequestModalInfoField[] = [
    { label: 'Nomor', value: req.number },
    { label: 'Pemohon', value: req.requester || '-' },
    { label: 'Pemanfaatan', value: req.pemanfaatan === 'corporate' ? `Corporate (${req.pemanfaatanDetail || '-'})` : `Project ${req.pemanfaatanDetail || '-'}` },
  ];

  if (req.type === 'peminjaman' && req.durationStart) {
    const durStr = req.durationEnd 
      ? `${req.durationStart} s.d. ${req.durationEnd} (${req.durationDays || 0} hari, ${req.durationHours || 0} jam)`
      : `${req.durationStart} s.d. - (Tanpa Tenggat Waktu)`;
    fields.push({ label: 'Durasi', value: durStr });
  }

  if (req.reasoning) {
    fields.push({ label: 'Alasan', value: req.reasoning });
  }

  return fields;
}
