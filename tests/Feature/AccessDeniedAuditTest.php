<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A blocked access attempt (role middleware, or an ownership abort_unless()
 * check in a controller) used to render its 403 and leave no trail — 403s
 * sit in the base Handler's default "don't report" list. App\Exceptions\Handler
 * now records every 403 to the same audit_logs table the IT-only Audit
 * Report page reads (event=access_denied), so staff can see what other
 * roles are trying to reach outside their remit.
 */
class AccessDeniedAuditTest extends TestCase
{
    use DatabaseTransactions;

    private function makeStaff(string $role): User
    {
        $user = User::create([
            'name' => 'Test '.$role,
            'email' => 'access-denied-'.$role.'-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
        $user->forceFill(['system_role' => $role])->save();

        return $user;
    }

    public function test_role_middleware_403_is_recorded_to_audit_log(): void
    {
        $finance = $this->makeStaff('finance');

        $this->actingAs($finance)->get(route('admin.staff.index'))->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'access_denied',
            'auditable_type' => User::class,
            'auditable_id' => $finance->id,
            'user_id' => $finance->id,
        ]);

        $entry = AuditLog::where('event', 'access_denied')->where('user_id', $finance->id)->latest()->first();
        $this->assertSame('admin.staff.index', $entry->new_values['route']);
        $this->assertSame('GET', $entry->new_values['method']);
    }

    public function test_successful_request_does_not_write_an_access_denied_entry(): void
    {
        $admin = $this->makeStaff('admin');

        $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'access_denied',
            'user_id' => $admin->id,
        ]);
    }
}
