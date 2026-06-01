<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\LoanApplication;


class AdminLoanMismatchNotification extends Mailable
{
    use Queueable, SerializesModels;
        public LoanApplication $application;
    public array $details;

    /**
     * Create a new message instance.
     */
    public function __construct(LoanApplication $application, array $details = [])
    {
        $this->application = $application;
        $this->details = $details;
    }

    public function build()
    {
        return $this->subject("⚠️ Loan Application Mismatch - Application #{$this->application->id}")
                    ->view('emails.admin.loan_mismatch')
                    ->with([
                        'application' => $this->application,
                        'details' => $this->details,
                    ]);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'view.name',
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
