<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingChargeBatchesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int $createdCount,
        public readonly int $pendingCount,
        public readonly int $billingMonth,
        public readonly int $billingYear,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').' — draft charge batches ready for review',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.pending-charge-batches',
        );
    }
}
