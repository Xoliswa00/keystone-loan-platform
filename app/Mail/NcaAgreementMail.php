<?php

namespace App\Mail;

use App\Models\NcaAgreement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class NcaAgreementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NcaAgreement $agreement) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->agreement->getTypeLabel().' — '.$this->agreement->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.nca_agreement',
            with: ['agreement' => $this->agreement],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(Storage::disk('local')->path($this->agreement->file_path))
                ->as($this->agreement->reference.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
