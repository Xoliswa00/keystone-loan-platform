<?php

namespace App\Notifications;

use App\Mail\LoanNotificationMail;
use App\Models\LoanRepayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
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
            'Payment Received',
            [
                'We received your payment of R'.number_format($this->repayment->payment_amount, 2).'.',
                'Your loan balance has been updated.',
            ],
            $this->repayment->loan
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'payment_received',
            'loan_id' => $this->repayment->loan_id,
            'repayment_id' => $this->repayment->id,
            'amount' => (float) $this->repayment->payment_amount,
            'message' => 'Payment of R'.number_format($this->repayment->payment_amount, 2).' received.',
        ];
    }
}
