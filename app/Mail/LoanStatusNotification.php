<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanStatusNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectLine;

    public $bodyLines; // array of paragraphs

    public $loan;

    // Default CCs for all loan emails
    protected $defaultCCs = [
        'bester12@outlook.com',
        'info@keystorecapital.co.za',
    ];

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, array $bodyLines = [], $loan = null)
    {
        $this->subjectLine = $subject;
        $this->bodyLines = $bodyLines;
        $this->loan = $loan;
    }

    public function build()
    {
        $email = $this->subject($this->subject)
            ->markdown('mail.status')
            ->with([
                'subject' => $this->subject,
                'bodyLines' => $this->bodyLines,
                'loan' => $this->loan,
            ]);

        // Automatically add default CCs to all LoanNotificationMail
        if (! empty($this->defaultCCs)) {
            $email->cc($this->defaultCCs);
        }

        return $email;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Loan Status Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.status',
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
