<?php

namespace App\Notifications;

use App\Mail\LoanNotificationMail;
use App\Models\LoanRepayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected LoanRepayment $repayment, protected float $dishonourFee) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): LoanNotificationMail
    {
        return (new LoanNotificationMail(
            'Debit Order Failed',
            [
                'Your scheduled debit order due '.optional($this->repayment->due_date)->format('d F Y').' did not go through.',
                'A dishonour fee of R'.number_format($this->dishonourFee, 2).' has been added to your outstanding balance.',
                'Please ensure funds are available before the next debit attempt, or contact us to arrange payment.',
            ],
            $this->repayment->loan
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'loan_id' => $this->repayment->loan_id,
            'repayment_id' => $this->repayment->id,
            'dishonour_fee' => $this->dishonourFee,
            'message' => 'Debit order failed — a R'.number_format($this->dishonourFee, 2).' dishonour fee was applied.',
        ];
    }
}
