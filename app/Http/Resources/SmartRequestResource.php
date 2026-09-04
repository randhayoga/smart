<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unified Smart Request Resource transforming request models for user history, manager review, and admin processing.
 *
 * @mixin \App\Models\Request\Request
 */
class SmartRequestResource extends JsonResource
{
    /**
     * Status display mappings.
     */
    protected const STATUS_MAP = [
        'wait' => 'Menunggu approval',
        'approve' => 'Di-approve',
        'confirm' => 'Dikonfirmasi Admin',
        'handover' => 'Serah Terima',
        'borrow' => 'Dipinjam',
        'return' => 'Dipinjam',
        'success' => 'Selesai',
        'reject' => 'Ditolak',
        'cancel' => 'Dibatalkan',
        'pending' => 'Pending',
        'partial' => 'Partial',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $startDate = $this->start_date instanceof \Carbon\CarbonInterface 
            ? $this->start_date 
            : ($this->start_date ? \Carbon\Carbon::parse($this->start_date) : null);
        $endDate = $this->end_date instanceof \Carbon\CarbonInterface 
            ? $this->end_date 
            : ($this->end_date ? \Carbon\Carbon::parse($this->end_date) : null);

        $durationDays = 0;
        $durationHours = 0;
        if ($startDate && $endDate) {
            $diff = $startDate->diff($endDate);
            $durationDays = $diff->days;
            $durationHours = $diff->h;
        }

        $pemanfaatanDetail = '-';
        if ($this->utilization === 'corporate') {
            $pemanfaatanDetail = $this->department?->org_name ?? $this->department?->name ?? '-';
        } else {
            if ($this->project) {
                $pemanfaatanDetail = $this->project->no_project 
                    ? "{$this->project->no_project} ({$this->project->project_name})" 
                    : ($this->project->project_name ?? '-');
            }
        }

        $createdAtFormatted = $this->created_at ? $this->created_at->format('d-m-Y H:i') : '-';

        $borrowPeriod = null;
        if ($startDate) {
            $borrowPeriod = $endDate 
                ? "{$startDate->format('d-m-Y H:i')} s.d. {$endDate->format('d-m-Y H:i')}"
                : "{$startDate->format('d-m-Y H:i')} s.d. - (Tanpa Tenggat Waktu)";
        }

        $data = [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number' => $this->request_number,
            'type' => $this->type_key,
            'pemanfaatan' => $this->utilization,
            'pemanfaatanDetail' => $pemanfaatanDetail,
            'destination' => $this->destination_name,
            'reasoning' => $this->reasoning,
            'durationStart' => $startDate ? $startDate->format('d-m-Y H:i') : null,
            'durationEnd' => $endDate ? $endDate->format('d-m-Y H:i') : null,
            'durationDays' => $durationDays,
            'durationHours' => $durationHours,
            'borrowPeriod' => $borrowPeriod,
            'status' => self::STATUS_MAP[$this->status] ?? $this->status,
            'raw_status' => $this->status,
            'rawStatus' => $this->status,
            'created_at' => $createdAtFormatted,
            'createdAt' => $createdAtFormatted,
        ];

        if ($this->relationLoaded('user') && $this->user) {
            $data['requester'] = $this->user->name ?? '-';
        }

        if ($this->relationLoaded('approver') && $this->approver) {
            $data['approver_name'] = $this->approver->name;
        }

        if ($this->relationLoaded('approval')) {
            $data['approval_by'] = $this->approval?->approver?->name;
            $data['approval_at'] = $this->approval?->decided_at?->format('d-m-Y H:i');
            $data['approval'] = $this->approval ? [
                'id' => $this->approval->id,
                'note' => $this->approval->note,
                'decision' => $this->approval->decision,
                'decided_at' => $this->approval->decided_at?->format('d-m-Y H:i'),
                'approver_name' => $this->approval->approver?->name,
            ] : null;
        }

        if ($this->relationLoaded('adminConfirmation')) {
            $data['confirmation_by'] = $this->adminConfirmation?->admin?->name;
            $data['confirmation_at'] = $this->adminConfirmation?->decided_at?->format('d-m-Y H:i');
        }

        if ($this->relationLoaded('handover')) {
            $data['handover_method'] = $this->handover ? ($this->handover->method === 'pickup' ? 'Ambil sendiri' : 'Diantar') : null;
            $data['handover_time'] = $this->handover?->scheduled_date?->format('d-m-Y H:i');
            $data['handover_location'] = $this->handover?->location;
            $data['handover_note'] = $this->handover?->note;
        }

        if ($this->relationLoaded('statusLogs')) {
            $data['return_confirmed_by'] = $this->statusLogs->first(fn($log) => $log->status_from === 'return' && $log->status_to === 'success')?->changer?->name;
            $data['logs'] = $this->statusLogs->map(function ($log) {
                $actorRole = 'User';
                $actorName = $log->changer->name ?? '-';
                if ($log->changer && $log->changer->role === 'admin') {
                    $actorRole = 'Admin';
                } else if ($log->changer && in_array($log->changer->role, ['manager', 'ifs_manager'])) {
                    $actorRole = 'Manager';
                }

                return [
                    'id' => $log->id,
                    'status_from' => $log->status_from,
                    'status_to' => $log->status_to,
                    'time' => $log->created_at ? $log->created_at->format('d-m-Y H:i') : '-',
                    'actor' => "{$actorRole}: {$actorName}",
                    'user' => $actorName,
                    'note' => $log->note ?? '',
                ];
            })->toArray();
        }

        if ($this->relationLoaded('items')) {
            $itemsResource = SmartRequestItemResource::collection($this->items)->resolve();
            $data['items'] = $itemsResource;

            $isAdminContext = ($request->user()?->isAdmin && ($request->routeIs('smart.inbox*') || $request->routeIs('smart.admin.*')));
            if ($isAdminContext || $request->attributes->get('with_stock')) {
                $allStockSufficient = true;
                foreach ($itemsResource as $item) {
                    if (isset($item['stockQuantity']) && $item['stockQuantity'] < $item['quantity']) {
                        $allStockSufficient = false;
                        break;
                    }
                }
                $data['is_stock_sufficient'] = $allStockSufficient;
            }
        }

        return $data;
    }
}
