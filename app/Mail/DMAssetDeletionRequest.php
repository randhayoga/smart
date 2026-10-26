<?php

namespace App\Mail;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetDeletionApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable notification sent to IFS Department Manager to request approval for asset deletion.
 */
class DMAssetDeletionRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The asset being reviewed.
     */
    public Asset $asset;

    /**
     * The deletion approval request record.
     */
    public ?AssetDeletionApproval $approval;

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
     * @param Asset $asset
     * @param AssetDeletionApproval|null $approval
     * @param string|null $recipientName
     */
    public function __construct(Asset $asset, ?AssetDeletionApproval $approval = null, ?string $recipientName = null)
    {
        $this->asset = $asset->loadMissing([
            'lot.model.brand',
            'lot.model.type.category',
            'location.parent',
        ]);

        $this->approval = $approval ?? AssetDeletionApproval::where('asset_id', $asset->id)
            ->where('decision', 'pending')
            ->latest('id')
            ->first();

        if ($this->approval) {
            $this->approval->loadMissing('requester');
        }

        $brand = $this->asset->lot?->model?->brand?->name ?? '';
        $assetName = $this->asset->lot?->model?->name ?? '';
        $this->brandAndName = trim("{$brand} {$assetName}") ?: $this->asset->number;

        $this->locationText = $this->asset->location?->full_name ?? '-';

        $this->recipientName = $recipientName;
        $this->actionUrl = url('/smart/approve-deletion?search=' . urlencode($this->asset->number));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[SMART] Asset Deletion Approval Request: {$this->asset->number} ({$this->brandAndName})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.dm_asset_deletion_request',
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
