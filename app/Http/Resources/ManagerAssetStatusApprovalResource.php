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
        $lifecycles = UnitLifecycle::with(['actor'])
            ->where('unit_id', $this->unit_id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($lc) {
                $duration = $lc->formatted_duration;

                $actorRole = 'System';
                $actorName = 'System';
                if ($lc->actor) {
                    $actorName = $lc->actor->name ?? '-';
                    if ($lc->actor->role === 'admin') {
                        $actorRole = 'Admin';
                    } else if (in_array($lc->actor->role, ['manager', 'ifs_manager'])) {
                        $actorRole = 'Manager';
                    } else {
                        $actorRole = 'User';
                    }
                }

                return [
                    'waktu' => $lc->start_date ? $lc->start_date->format('d-m-Y H:i:s') : '-',
                    'status' => $lc->status ?? '-',
                    'action_type' => $lc->action_type ?? '-',
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
            'proposed_condition' => $this->proposed_condition,
            'previous_condition' => $this->previous_condition,
            'proposed_status' => $this->proposed_condition,
            'previous_status' => $this->previous_status,
            'status_label' => $this->proposed_condition ?? '-',
            'decision' => $this->decision,
            'note' => $this->note,
            'requested_by' => $this->requester->name ?? '-',
            'requested_at' => $this->requested_at ? $this->requested_at->format('d-m-Y H:i') : '-',
            'decided_at' => $this->decided_at ? $this->decided_at->format('d-m-Y H:i') : null,
            'approver_name' => $this->approver->name ?? null,
            'memo_url' => $this->memo_url,
            'lost_doc_url' => $this->lost_doc_url,
            'unit_details' => [
                'id' => $unit->id,
                'number' => $unit->number ?? '-',
                'status' => $unit->status ?? '-',
                'condition' => $unit->condition ?? '-',
                'price' => $unit->price ? number_format($unit->price, 0, ',', '.') : '-',
                'image_url' => $unit->image_url ? '/media/' . $unit->image_url : ($lot->image_url ? '/media/' . $lot->image_url : null),
                'vehicle_registration' => $unit->vehicle_registration ?? '-',
                'location' => $unit->location->name ?? '-',
                'floor' => $unit->floor->name ?? null,
                'room' => $unit->room->name ?? null,

                'lot_code' => $lot->number ?? '-',
                'organizer' => $lot->organizer->name ?? '-',
                'date_of_receipt' => $lot->date_of_receipt ? $lot->date_of_receipt->format('d-m-Y') : '-',
                'age' => $lot ? $lot->age : null,
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
