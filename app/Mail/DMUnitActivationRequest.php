<?php

namespace App\Mail;

use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitActivationApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable notification sent to IFS Department Manager to request validation for newly created asset units.
 */
class DMUnitActivationRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The unit asset being validated.
     */
    public Unit $unit;

    /**
     * The activation approval request record.
     */
    public ?UnitActivationApproval $approval;

    /**
     * Direct URL link for the recipient to view/validate the request.
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
     * @param UnitActivationApproval|null $approval
     * @param string|null $recipientName
     */
    public function __construct(Unit $unit, ?UnitActivationApproval $approval = null, ?string $recipientName = null)
    {
        $this->unit = $unit->loadMissing([
            'lot.barang.brand',
            'lot.barang.subcategory.category',
            'location.parent',
        ]);

        $this->approval = $approval ?? UnitActivationApproval::where('unit_id', $unit->id)
            ->where('decision', 'pending')
            ->latest('id')
            ->first();

        if ($this->approval) {
            $this->approval->loadMissing('requester');
        }

        $brand = $this->unit->lot?->barang?->brand?->name ?? '';
        $assetName = $this->unit->lot?->barang?->name ?? '';
        $this->brandAndName = trim("{$brand} {$assetName}") ?: $this->unit->number;

        $this->locationText = $this->unit->location?->full_name ?? '-';

        $this->recipientName = $recipientName;
        $this->actionUrl = url('/smart/approve-activation?search=' . urlencode($this->unit->number));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[SMART] New Asset Validation: {$this->unit->number} ({$this->brandAndName})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.dm_unit_activation_request',
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
