<?php

namespace App\Mail;

use App\Models\WebLeadEmailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebLeadInternalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WebLeadEmailDelivery $delivery,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = trim((string) data_get($this->delivery->mailer_snapshot, 'from_address', ''));
        $fromName = trim((string) data_get($this->delivery->mailer_snapshot, 'from_name', ''));
        $subject = trim((string) data_get($this->delivery->payload, 'subject', 'Web lead mới từ website'));

        return new Envelope(
            from: $fromAddress !== '' ? new Address($fromAddress, $fromName !== '' ? $fromName : null) : null,
            subject: $subject !== '' ? $subject : 'Web lead mới từ website',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.web-lead.internal-notification',
            with: [
                'payload' => (array) ($this->delivery->payload ?? []),
                'delivery' => $this->delivery,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
