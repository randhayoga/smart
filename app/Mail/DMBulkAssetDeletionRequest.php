<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable notification sent to IFS Department Manager to request approval for a batch of asset deletions.
 */
class DMBulkAssetDeletionRequest extends Mailable
{
    use Queueable, SerializesModels;

    public int $count;
    public string $proposedCondition;
    public array $sampleAssets;
    public ?string $recipientName;
    public ?string $requesterName;
    public string $actionUrl;

    /**
     * Create a new message instance.
     *
     * @param int $count
     * @param string $proposedCondition
     * @param array $sampleAssets Array of sample asset items [['number' => ..., 'name' => ..., 'location' => ...]]
     * @param User|null $requester
     * @param string|null $recipientName
     */
    public function __construct(
        int $count,
        string $proposedCondition,
        array $sampleAssets = [],
        ?User $requester = null,
        ?string $recipientName = null
    ) {
        $this->count = $count;
        $this->proposedCondition = $proposedCondition;
        $this->sampleAssets = $sampleAssets;
        $this->recipientName = $recipientName;
        $this->requesterName = $requester?->name ?? null;
        $this->actionUrl = url('/smart/approve-deletion');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[SMART] Asset Deletion Batch Approval Request: {$this->count} Units ({$this->proposedCondition})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.dm_bulk_asset_deletion_request',
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
