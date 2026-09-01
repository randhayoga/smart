<?php

namespace App\Mail;

use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable notification sent to IFS Department Manager to request approval for asset condition or status changes.
 */
class DMUnitStatusRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The unit asset being reviewed.
     */
    public Unit $unit;

    /**
     * The status approval request record.
     */
    public ?UnitStatusApproval $approval;

    /**
     * Direct URL link for the recipient to view/approve the request.
     */
    public string $actionUrl;

    /**
     * Combined brand and item name for display in the email.
     */
    public string $brandAndName;

    /**
     * Formatted location string (Location - Floor - Room).
     */
    public string $locationText;

    /**
     * Name of the recipient manager.
     */
    public ?string $recipientName;

    /**
     * Create a new message instance.
     *
     * @param Unit $unit
     * @param UnitStatusApproval|null $approval
     * @param string|null $recipientName
     */
    public function __construct(Unit $unit, ?UnitStatusApproval $approval = null, ?string $recipientName = null)
    {
        $this->unit = $unit->loadMissing([
            'lot.barang.brand',
            'lot.barang.subcategory.category',
            'location',
            'floor',
            'room',
        ]);

        $this->approval = $approval ?? UnitStatusApproval::where('unit_id', $unit->id)
            ->where('decision', 'pending')
            ->latest('id')
            ->first();

        if ($this->approval) {
            $this->approval->loadMissing('requester');
        }

        $brand = $this->unit->lot?->barang?->brand?->name ?? '';
        $assetName = $this->unit->lot?->barang?->name ?? '';
        $this->brandAndName = trim("{$brand} {$assetName}") ?: $this->unit->number;

        $locParts = array_filter([
            $this->unit->location?->name,
            $this->unit->floor?->name,
            $this->unit->room?->name,
        ]);
        $this->locationText = !empty($locParts) ? implode(' - ', $locParts) : '-';

        $this->recipientName = $recipientName;
        $this->actionUrl = url('/smart/approve-status?search=' . urlencode($this->unit->number));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[SMART] Permohonan Persetujuan Status Aset: {$this->unit->number} ({$this->brandAndName})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.dm_unit_status_request',
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
