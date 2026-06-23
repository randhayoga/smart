<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Inventory\UnitLifecycle;

class ManagerAssetStatusApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unit = $this->unit;
        $lot = $unit->lot ?? null;
        $barang = $lot->barang ?? null;

        // Fetch lifecycles for audit trail
        $lifecycles = UnitLifecycle::with(['requester', 'approver'])
            ->where('unit_id', $this->unit_id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($lc) {
                $duration = '-';
                if ($lc->start_date && $lc->end_date) {
                    $duration = $lc->start_date->diffInDays($lc->end_date);
                }

                $actorRole = 'User';
                $actorName = $lc->requester->name ?? '-';
                if ($lc->requester && $lc->requester->role === 'admin') {
                    $actorRole = 'Admin';
                } else if ($lc->requester && in_array($lc->requester->role, ['manager', 'ifs_manager'])) {
                    $actorRole = 'Manager';
                }

                return [
                    'waktu' => $lc->start_date ? $lc->start_date->format('d-m-Y H:i') : '-',
                    'aksi_status' => $lc->status ?? '-',
                    'aktor' => "{$actorRole}: {$actorName}",
                    'durasi' => $duration,
                    'catatan' => $lc->note ?? '-',
                ];
            });

        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'asset_code' => $unit->number ?? '-',
            'category' => $barang->subcategory->category->name ?? '-',
            'subcategory' => $barang->subcategory->name ?? '-',
            'brand' => $barang->brand->name ?? '-',
            'nama' => $barang->name ?? '-',
            'specification' => $barang->specification ?? '-',
            'proposed_status' => $this->proposed_status,
            'previous_status' => $this->previous_status,
            'status_label' => $this->proposed_status ?? '-',
            'decision' => $this->decision,
            'note' => $this->note,
            'requested_by' => $this->requester->name ?? '-',
            'requested_at' => $this->requested_at ? $this->requested_at->format('d-m-Y H:i') : '-',
            'decided_at' => $this->decided_at ? $this->decided_at->format('d-m-Y H:i') : null,
            'approver_name' => $this->approver->name ?? null,
            'doc_url' => $this->doc_url,
            'unit_details' => [
                'id' => $unit->id,
                'number' => $unit->number ?? '-',
                'status' => $unit->status ?? '-',
                'condition' => $unit->condition ?? '-',
                'price' => $unit->price ? number_format($unit->price, 0, ',', '.') : '-',
                'image_url' => $unit->image_url ? '/storage/' . $unit->image_url : ($lot->image_url ? '/storage/' . $lot->image_url : null),
                'vehicle_registration' => $unit->vehicle_registration ?? '-',
                'location' => $unit->location->name ?? '-',
                'floor' => $unit->floor->name ?? null,
                'room' => $unit->room->name ?? null,

                'lot_code' => $lot->number ?? '-',
                'organizer' => $lot->organizer->name ?? '-',
                'date_of_receipt' => $lot->date_of_receipt ? $lot->date_of_receipt->format('d-m-Y') : '-',
                'vendor' => $lot->vendor->name ?? '-',
                'po_number' => $lot->po_number ?? '-',
                'barang_code' => $barang->number ?? '-',
                'barang_nama' => $barang->name ?? '-',
                'barang_spec' => $barang->specification ?? '-',
                'barang_unit' => $barang->uom->name ?? 'pcs',

                'lifecycles' => $lifecycles,
            ]
        ];
    }
}
