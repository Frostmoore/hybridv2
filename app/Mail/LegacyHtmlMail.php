<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email in stile legacy: corpo HTML costruito inline dal chiamante,
 * mittente fisso (MAIL_FROM_ADDRESS) con display-name = nome agenzia,
 * eventuale ZIP allegato. Replica make_mailer() del _bootstrap.php.
 */
class LegacyHtmlMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
        public string $fromName = 'noreply',
        public ?string $attachmentPath = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->fromName),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlBody);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->attachmentPath !== null && is_file($this->attachmentPath)) {
            return [Attachment::fromPath($this->attachmentPath)];
        }

        return [];
    }
}
