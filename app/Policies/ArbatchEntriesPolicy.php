<?php

namespace App\Policies;

use App\Models\arbatch_entries;
use App\Models\User;

class ArbatchEntriesPolicy
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
    public function view(User $user, arbatch_entries $arbatchEntries): bool
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
    public function update(User $user, arbatch_entries $arbatchEntries): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, arbatch_entries $arbatchEntries): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, arbatch_entries $arbatchEntries): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, arbatch_entries $arbatchEntries): bool
    {
        //
    }
}
