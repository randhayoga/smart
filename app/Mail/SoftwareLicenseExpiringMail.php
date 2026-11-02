<?php

namespace App\Mail;

use App\Models\Inventory\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable notification sent to Admins when a software asset license is nearing expiration.
 */
class SoftwareLicenseExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public Asset $asset;
    public int $daysRemaining;
    public ?string $recipientName;
    public string $brandAndName;
    public string $softwareName;
    public string $expiryDate;
    public string $locationText;
    public string $actionUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Asset $asset, int $daysRemaining, ?string $recipientName = null)
    {
        $this->asset = $asset->loadMissing([
            'lot.model.brand',
            'lot.model.type.category',
            'location.parent',
        ]);

        $this->daysRemaining = $daysRemaining;
        $this->recipientName = $recipientName;

        $brand = $this->asset->lot?->model?->brand?->name ?? '';
        $assetName = $this->asset->lot?->model?->name ?? '';
        $this->brandAndName = trim("{$brand} {$assetName}") ?: $this->asset->number;
        $this->softwareName = $assetName ?: $this->brandAndName;

        $this->expiryDate = $this->asset->license_exp_date ? $this->asset->license_exp_date->format('d M Y') : '-';
        $this->locationText = $this->asset->location?->full_name ?? '-';
        $this->actionUrl = url('/smart/inventory?search=' . urlencode($this->asset->number));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[SMART] Software License Expiring in {$this->daysRemaining} Days: {$this->asset->number} ({$this->brandAndName})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.software_license_expiring',
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
