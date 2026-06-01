<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanNotificationMail extends Mailable
{
    use Queueable, SerializesModels;
    public $subjectLine;
    public $bodyLines; // array of paragraphs
    public $loan;
      // Default CCs for all loan emails
    protected $defaultCCs = [
        'ligerholdings672@gmail.com',
        'bester12@outlook.com',
        'info@ligerholding.co.za',
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
        if (!empty($this->defaultCCs)) {
            $email->cc($this->defaultCCs);
        }

        return $email;
    }

    /**
     * Get the message envelope.
     */
   

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
