<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUnitStatusApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'unit_number' => $this->unit->number ?? '-',
            'proposed_status' => $this->proposed_status,
            'previous_status' => $this->previous_status,
            'decision' => $this->decision,
            'note' => $this->note,
            'requester_name' => $this->requester->name ?? '-',
            'approver_name' => $this->approver->name ?? '-',
            'requested_at' => $this->requested_at ? $this->requested_at->format('d/m/Y H:i') : '-',
            'decided_at' => $this->decided_at ? $this->decided_at->format('d/m/Y H:i') : '-',
        ];
    }
}
