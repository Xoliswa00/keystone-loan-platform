<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Implements ShouldQueue so a real queue worker takes the SMTP round-trip
 * off the request entirely — the moment QUEUE_CONNECTION moves off 'sync',
 * this closes the timing side-channel in sendCode() for real. Until then,
 * sendCode() also pads its own response time as a stopgap (see its
 * docblock) since 'sync' makes queuing behave synchronously anyway.
 */
class InvestorOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your investor portal access code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.investor_otp',
            with: ['code' => $this->code],
        );
    }
}
