<?php

namespace Tests\Feature;

use App\Models\ExportProfile;
use App\Models\ExportRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\MakesReportingFixtures;
use Tests\TestCase;

class ExportAdminTest extends TestCase
{
    use DatabaseTransactions, MakesReportingFixtures;

    private function staff(string $role): User
    {
        $u = User::create([
            'name' => 'Staff '.$role.uniqid(),
            'email' => 'exp-'.$role.'-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
        $u->forceFill(['system_role' => $role])->save();

        return $u;
    }

    private function recipient(array $o = []): ExportRecipient
    {
        return ExportRecipient::create(array_merge([
            'name' => 'Bureau', 'slug' => 'bureau-'.uniqid(), 'kind' => 'bureau',
            'transport' => 'download', 'active' => false,
        ], $o));
    }

    public function test_dashboard_gating(): void
    {
        foreach (['loan_officer', 'finance'] as $role) {
            $this->actingAs($this->staff($role))->get(route('admin.exports.dashboard'))->assertForbidden();
        }
        foreach (['admin', 'it_admin'] as $role) {
            $this->actingAs($this->staff($role))->get(route('admin.exports.dashboard'))->assertOk();
        }
    }

    public function test_all_admin_pages_render(): void
    {
        $admin = $this->staff('admin');
        $r = $this->recipient(['lawful_basis' => 'x', 'active' => true]);
        $p = ExportProfile::create([
            'export_recipient_id' => $r->id, 'name' => 'P', 'slug' => 'p-'.uniqid(),
            'dataset' => 'account_snapshot', 'format' => 'csv', 'delimiter' => ',', 'enclosure' => '"',
            'include_header' => true, 'line_ending' => 'lf',
            'columns' => [['source' => 'account_no', 'label' => 'A', 'format' => 'string']],
            'filename_pattern' => '{profile_slug}_{period}.csv', 'schedule_day_of_month' => 5,
            'period_offset' => 1, 'active' => false,
        ]);

        foreach ([
            route('admin.exports.dashboard'),
            route('admin.exports.recipients.index'),
            route('admin.exports.recipients.create'),
            route('admin.exports.recipients.edit', $r),
            route('admin.exports.profiles.index'),
            route('admin.exports.profiles.create'),
            route('admin.exports.profiles.edit', $p),
            route('admin.exports.runs.index'),
            route('admin.exports.disputes.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_recipient_creation_is_admin_only(): void
    {
        $this->actingAs($this->staff('it_admin'))->get(route('admin.exports.recipients.create'))->assertForbidden();
        $this->actingAs($this->staff('admin'))->get(route('admin.exports.recipients.create'))->assertOk();
    }

    public function test_admin_creates_recipient_with_audit(): void
    {
        $this->actingAs($this->staff('admin'))
            ->post(route('admin.exports.recipients.store'), [
                'name' => 'SACRRA via TransUnion',
                'slug' => 'sacrra-tu',
                'kind' => 'bureau',
                'transport' => 'download',
                'lawful_basis' => 'Loan agreement clause 14 — monthly bureau reporting.',
            ])
            ->assertRedirect(route('admin.exports.recipients.index'));

        $this->assertDatabaseHas('export_recipients', ['slug' => 'sacrra-tu', 'kind' => 'bureau']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => ExportRecipient::class]);
    }

    public function test_recipient_cannot_activate_without_lawful_basis(): void
    {
        $r = $this->recipient(['lawful_basis' => null, 'active' => false]);

        $this->actingAs($this->staff('admin'))
            ->post(route('admin.exports.recipients.toggle', $r))
            ->assertSessionHas('error');

        $this->assertFalse($r->fresh()->active);
    }

    public function test_profile_rejects_pii_column_for_non_bureau_recipient(): void
    {
        $r = $this->recipient(['kind' => 'partner']);

        $this->actingAs($this->staff('it_admin'))
            ->post(route('admin.exports.profiles.store'), $this->profilePayload($r, [
                ['source' => 'id_number', 'label' => 'ID', 'format' => 'string'],
            ]))
            ->assertSessionHasErrors('columns_json');

        $this->assertDatabaseMissing('export_profiles', ['export_recipient_id' => $r->id]);
    }

    public function test_profile_rejects_filename_traversal(): void
    {
        $r = $this->recipient();

        $this->actingAs($this->staff('it_admin'))
            ->post(route('admin.exports.profiles.store'), $this->profilePayload($r, [
                ['source' => 'account_no', 'label' => 'A', 'format' => 'string'],
            ], ['filename_pattern' => '../{period}.csv']))
            ->assertSessionHasErrors('filename_pattern');
    }

    public function test_profile_created_with_valid_columns(): void
    {
        $r = $this->recipient();

        $this->actingAs($this->staff('it_admin'))
            ->post(route('admin.exports.profiles.store'), $this->profilePayload($r, [
                ['source' => 'account_no', 'label' => 'Account', 'format' => 'string'],
                ['source' => 'id_number', 'label' => 'ID Number', 'format' => 'string'],
                ['source' => 'arrears_amount', 'label' => 'Arrears', 'format' => 'money'],
            ]))
            ->assertRedirect(route('admin.exports.profiles.index'));

        $profile = ExportProfile::where('export_recipient_id', $r->id)->firstOrFail();
        $this->assertCount(3, $profile->columns);
        $this->assertSame('account_no', $profile->columns[0]['source']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => ExportProfile::class]);
    }

    public function test_preview_runs_without_transmitting(): void
    {
        config(['exports.enabled' => true]);
        $r = $this->recipient();
        $p = ExportProfile::create([
            'export_recipient_id' => $r->id, 'name' => 'P', 'slug' => 'p-'.uniqid(),
            'dataset' => 'account_snapshot', 'format' => 'csv', 'delimiter' => ',', 'enclosure' => '"',
            'include_header' => true, 'line_ending' => 'lf',
            'columns' => [['source' => 'account_no', 'label' => 'A', 'format' => 'string']],
            'filename_pattern' => '{profile_slug}_{period}.csv', 'schedule_day_of_month' => 5,
            'period_offset' => 1, 'active' => true,
        ]);

        $this->actingAs($this->staff('it_admin'))
            ->post(route('admin.exports.profiles.preview', $p), ['period' => '2026-01'])
            ->assertOk();
    }

    private function profilePayload(ExportRecipient $r, array $columns, array $overrides = []): array
    {
        return array_merge([
            'export_recipient_id' => $r->id,
            'name' => 'Monthly '.uniqid(),
            'slug' => 'monthly-'.uniqid(),
            'dataset' => 'account_snapshot',
            'format' => 'csv',
            'delimiter' => ',',
            'enclosure' => '"',
            'line_ending' => 'lf',
            'include_header' => '1',
            'filename_pattern' => '{recipient_slug}_{period}.csv',
            'schedule_day_of_month' => 5,
            'period_offset' => 1,
            'columns_json' => json_encode($columns),
        ], $overrides);
    }
}
