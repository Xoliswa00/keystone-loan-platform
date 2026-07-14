<?php

namespace App\Policies;

use App\Models\Loan_fee_snaps;
use App\Models\User;

class LoanFeeSnapsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Loan_fee_snaps $loanFeeSnaps): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Loan_fee_snaps $loanFeeSnaps): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Loan_fee_snaps $loanFeeSnaps): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Loan_fee_snaps $loanFeeSnaps): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Loan_fee_snaps $loanFeeSnaps): bool
    {
        //
    }
}
