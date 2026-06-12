<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable di prova per verificare la configurazione SMTP
 * (in prod: Aruba, stesso server del legacy).
 * Invio manuale: php artisan tinker → Mail::to('…')->send(new TestMail());
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'HybridV2 — test configurazione mail',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test',
        );
    }
}
