<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoanNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $subjectLine;

    public array $bodyLines;

    public mixed $loan;

    public function __construct(string $subject, array $bodyLines = [], mixed $loan = null)
    {
        $this->subjectLine = $subject;
        $this->bodyLines = $bodyLines;
        $this->loan = $loan;
    }

    public function build(): self
    {
        $mail = $this->subject($this->subjectLine)
            ->markdown('mail.status')
            ->with([
                'subjectLine' => $this->subjectLine,
                'bodyLines' => $this->bodyLines,
                'loan' => $this->loan,
            ]);

        // CC from Company settings table (live, cached) → fallback to .env config
        try {
            $company = \App\Models\Company::settings();
            $ccs = $company ? $company->getNotificationCcArray()
                            : array_filter(explode(',', config('mail.loan_notification_cc', '')));
        } catch (\Exception $e) {
            $ccs = [];
        }

        if (! empty($ccs)) {
            $mail->cc($ccs);
        }

        return $mail;
    }

    public function attachments(): array
    {
        return [];
    }
}
