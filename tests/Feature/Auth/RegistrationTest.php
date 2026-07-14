<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'phone' => '0821234567',
            'address' => '1 Main Street, Cape Town',
            'ID_Number' => '9001015800086',
            'salary_payment_day' => 25,
            'ID_copy' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            'terms' => '1',
            'popia_consent' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('loanapplications.create'));
    }
}
