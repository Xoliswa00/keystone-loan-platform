<?php

namespace App\Notifications;

use App\Mail\LoanNotificationMail;
use App\Models\LoanRepayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentReversedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected LoanRepayment $repayment) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): LoanNotificationMail
    {
        return (new LoanNotificationMail(
            'Payment Reversed',
            [
                'Your payment of R'.number_format(abs((float) $this->repayment->payment_amount), 2).' has been reversed.',
                'Your loan balance has been adjusted accordingly. Contact us if you believe this is an error.',
            ],
            $this->repayment->loan
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'payment_reversed',
            'loan_id' => $this->repayment->loan_id,
            'repayment_id' => $this->repayment->id,
            'amount' => (float) $this->repayment->payment_amount,
            'message' => 'A payment of R'.number_format(abs((float) $this->repayment->payment_amount), 2).' was reversed.',
        ];
    }
}
