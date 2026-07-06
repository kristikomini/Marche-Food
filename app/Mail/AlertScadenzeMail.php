<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertScadenzeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $inScadenza,
        public array $scaduti,
        public array $certificatiInScadenza,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[HACCP] Alert Scadenze — Marche International Food',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.alert_scadenze');
    }
}
