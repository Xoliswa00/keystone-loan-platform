<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        AuditLog::record('created', $user, [], [
            'name' => $user->name,
            'email' => $user->email,
            'system_role' => $user->system_role,
        ]);
    }

    public function updated(User $user): void
    {
        $dirty = $this->excludeSensitive($user->getDirty());

        if (empty($dirty)) {
            return;
        }

        $old = array_intersect_key($this->excludeSensitive($user->getOriginal()), $dirty);

        AuditLog::record('updated', $user, $old, $dirty);
    }

    public function deleted(User $user): void
    {
        AuditLog::record('deleted', $user, [], ['email' => $user->email]);
    }

    /**
     * Credential material never belongs in an audit trail, hashed or not.
     *
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    private function excludeSensitive(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(['password', 'remember_token', 'updated_at']));
    }
}
