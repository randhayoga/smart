<?php

namespace App\Mail;

use App\Models\Request\Request as SmartRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable notification sent to the requester when their request is rejected by the manager.
 */
class RequesterRequestRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public SmartRequest $smartRequest;
    public User $manager;
    public string $type;
    public string $reason;
    public string $detailUrl;
    public string $requesterName;
    public string $managerName;
    public string $destinationName;
    public bool $isBorrow;
    public ?string $borrowPeriod;
    public array $formattedItems;

    /**
     * Create a new message instance.
     *
     * @param SmartRequest $request
     * @param User $manager
     * @param string|null $reason
     */
    public function __construct(SmartRequest $request, User $manager, ?string $reason = null)
    {
        $this->smartRequest = $request->loadMissing([
            'user',
            'department',
            'project',
            'items.model.brand',
            'items.model.type.category',
            'items.model.uom',
            'items.type.category',
            'items.type.models.uom',
        ]);

        $this->manager = $manager;
        $this->type = $request->type_name;
        $this->requesterName = $request->user?->name ?? 'Pengguna';
        $this->managerName = $manager->name ?? 'Manager';
        $this->destinationName = $request->destination_name;
        $this->isBorrow = $request->isBorrow();
        $this->reason = !empty($reason) ? $reason : 'Tidak ada alasan spesifik yang dicantumkan.';

        $this->borrowPeriod = null;
        if ($this->isBorrow) {
            $firstItem = $request->items->first();
            if ($firstItem && $firstItem->start_date) {
                $start = $firstItem->start_date->format('d-m-Y H:i');
                $end = $firstItem->end_date ? $firstItem->end_date->format('d-m-Y H:i') : 'Selesai';
                $this->borrowPeriod = "{$start} s.d. {$end}";
            }
        }

        $this->formattedItems = $request->items->map(function ($item) {
            $brand = $item->model?->brand?->name;
            $name = $item->model?->name ?? $item->type?->name ?? 'Barang';
            $fullName = trim("{$brand} {$name}") ?: 'Barang';
            $spec = $item->model?->specification ?? '';
            $category = $item->model?->type?->category?->name ?? $item->type?->category?->name ?? '-';
            $uom = $item->model?->uom?->name ?? $item->type?->models?->first()?->uom?->name ?? '';

            return [
                'name' => $fullName,
                'spec' => $spec,
                'category' => $category,
                'quantity' => $item->quantity_requested,
                'uom' => $uom,
            ];
        })->toArray();

        $this->detailUrl = url('/smart/history/' . ($request->uuid ?? $request->id));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $typeLabel = $this->smartRequest->isBorrow() ? 'Borrowing' : 'Request';
        return new Envelope(
            subject: "[SMART] {$typeLabel} Request Rejected [{$this->smartRequest->request_number}]",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.requester_request_rejected',
            with: [
                'request' => $this->smartRequest,
                'manager' => $this->manager,
                'type' => $this->type,
                'reason' => $this->reason,
                'requesterName' => $this->requesterName,
                'managerName' => $this->managerName,
                'destinationName' => $this->destinationName,
                'isBorrow' => $this->isBorrow,
                'borrowPeriod' => $this->borrowPeriod,
                'items' => $this->formattedItems,
                'detailUrl' => $this->detailUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
