<?php

namespace App\Mail;

use App\Models\Inventory\Lot;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable notification sent to IFS Department Manager to request validation for newly created batch assets.
 */
class DMBulkAssetActivationRequest extends Mailable
{
    use Queueable, SerializesModels;

    public Lot $lot;
    public int $count;
    public string $firstNumber;
    public string $lastNumber;
    public string $brandAndName;
    public string $locationText;
    public ?string $recipientName;
    public ?string $requesterName;
    public string $actionUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Lot $lot,
        int $count,
        string $firstNumber,
        string $lastNumber,
        ?string $locationText = null,
        ?User $requester = null,
        ?string $recipientName = null
    ) {
        $this->lot = $lot->loadMissing([
            'model.brand',
            'model.type.category',
        ]);

        $this->count = $count;
        $this->firstNumber = $firstNumber;
        $this->lastNumber = $lastNumber;

        $brand = $this->lot->model?->brand?->name ?? '';
        $assetName = $this->lot->model?->name ?? '';
        $this->brandAndName = trim("{$brand} {$assetName}") ?: ($this->lot->number ?? 'Aset Baru');

        $this->locationText = $locationText ?? '-';
        $this->recipientName = $recipientName;
        $this->requesterName = $requester?->name ?? null;
        $this->actionUrl = url('/smart/approve-activation');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[SMART] New Asset Batch Validation: {$this->count} Units ({$this->brandAndName})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.dm_bulk_asset_activation_request',
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
