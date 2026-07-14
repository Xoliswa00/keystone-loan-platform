<?php

namespace App\Policies;

use App\Models\cashbook_batches;
use App\Models\User;

class CashbookBatchesPolicy
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
    public function view(User $user, cashbook_batches $cashbookBatches): bool
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
    public function update(User $user, cashbook_batches $cashbookBatches): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, cashbook_batches $cashbookBatches): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, cashbook_batches $cashbookBatches): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, cashbook_batches $cashbookBatches): bool
    {
        //
    }
}
