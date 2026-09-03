<?php

namespace App\Mail;

use App\Models\AdmUser;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Mailable notification sent to Department Manager or Project Manager for request approval.
 */
class ManagerRequestApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public SmartRequest $smartRequest;
    public AdmUser $manager;
    public string $type;
    public string $actionUrl;
    public string $loginUrl;
    public string $recipientName;
    public string $requesterName;
    public string $destinationName;
    public bool $isBorrow;
    public ?string $borrowPeriod;
    public array $formattedItems;

    /**
     * Create a new message instance.
     *
     * @param SmartRequest $request
     * @param AdmUser $manager
     * @param string $type 'Peminjaman' | 'Permintaan'
     */
    public function __construct(SmartRequest $request, AdmUser $manager, string $type = 'Permintaan')
    {
        $this->smartRequest = $request->loadMissing([
            'user',
            'department',
            'project',
            'items.barang.brand',
            'items.barang.subcategory.category',
            'items.barang.uom',
            'items.subcategory.category',
            'items.subcategory.barangs.uom',
        ]);

        $this->manager = $manager;
        $this->type = $request->type_name;
        $this->recipientName = $manager->name;
        $this->requesterName = $request->user?->name ?? 'Pengguna';
        $this->destinationName = $request->destination_name;
        $this->isBorrow = $request->isBorrow();

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
            $brand = $item->barang?->brand?->name;
            $name = $item->barang?->name ?? $item->subcategory?->name ?? 'Barang';
            $fullName = trim("{$brand} {$name}") ?: 'Barang';
            $spec = $item->barang?->specification ?? '';
            $category = $item->barang?->subcategory?->category?->name ?? $item->subcategory?->category?->name ?? '-';
            $uom = $item->barang?->uom?->name ?? $item->subcategory?->barangs?->first()?->uom?->name ?? '';

            return [
                'name' => $fullName,
                'spec' => $spec,
                'category' => $category,
                'quantity' => $item->quantity_requested,
                'uom' => $uom,
            ];
        })->toArray();

        // 48-hour temporary HMAC-signed URL for zero-login secure approval
        $this->actionUrl = URL::temporarySignedRoute(
            'smart.external-approval.show',
            now()->addHours(48),
            ['request' => $request->id]
        );

        // Standard login URL fallback with search filter for the request number
        $this->loginUrl = url('/smart/approve') . '?search=' . urlencode($request->request_number);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[SMART] Permohonan Persetujuan: {$this->type} Baru [{$this->smartRequest->request_number}]",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.manager_request_approval',
            with: [
                'request' => $this->smartRequest,
                'recipientName' => $this->recipientName,
                'requesterName' => $this->requesterName,
                'destinationName' => $this->destinationName,
                'type' => $this->type,
                'isBorrow' => $this->isBorrow,
                'borrowPeriod' => $this->borrowPeriod,
                'items' => $this->formattedItems,
                'actionUrl' => $this->actionUrl,
                'loginUrl' => $this->loginUrl,
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
