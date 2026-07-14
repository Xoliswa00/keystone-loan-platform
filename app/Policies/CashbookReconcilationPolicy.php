<?php

namespace App\Policies;

use App\Models\cashbook_reconcilation;
use App\Models\User;

class CashbookReconcilationPolicy
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
    public function view(User $user, cashbook_reconcilation $cashbookReconcilation): bool
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
    public function update(User $user, cashbook_reconcilation $cashbookReconcilation): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, cashbook_reconcilation $cashbookReconcilation): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, cashbook_reconcilation $cashbookReconcilation): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, cashbook_reconcilation $cashbookReconcilation): bool
    {
        //
    }
}
