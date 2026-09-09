<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DataExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $profileName,
        public string $period,
        public int $rowCount,
        public string $attachmentPath,
        public string $attachmentName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Data export — {$this->profileName} — {$this->period}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.data_export',
            with: [
                'profileName' => $this->profileName,
                'period' => $this->period,
                'rowCount' => $this->rowCount,
                'fileName' => $this->attachmentName,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->attachmentPath)
                ->as($this->attachmentName)
                ->withMime('text/csv'),
        ];
    }
}
