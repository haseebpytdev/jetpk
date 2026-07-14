<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSignupValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_email_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->withSession(['register_security_answer' => 7])->post('/register', $this->validPayload([
            'email' => 'taken@example.com',
            'security_answer' => '7',
        ]));

        $response->assertSessionHasErrors(['email']);
    }

    public function test_invalid_email_rejected(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->withSession(['register_security_answer' => 7])->post('/register', $this->validPayload([
            'email' => 'bad-email',
            'security_answer' => '7',
        ]));

        $response->assertSessionHasErrors(['email']);
    }

    public function test_malformed_email_rejected(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->withSession(['register_security_answer' => 7])->post('/register', $this->validPayload([
            'email' => 'user@gm*l.com',
            'security_answer' => '7',
        ]));

        $response->assertSessionHasErrors(['email']);
    }

    public function test_first_name_special_characters_rejected(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->withSession(['register_security_answer' => 7])->post('/register', $this->validPayload([
            'first_name' => 'Ali#1',
            'security_answer' => '7',
        ]));

        $response->assertSessionHasErrors(['first_name']);
    }

    public function test_last_name_special_characters_rejected(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->withSession(['register_security_answer' => 7])->post('/register', $this->validPayload([
            'last_name' => 'Khan@',
            'security_answer' => '7',
        ]));

        $response->assertSessionHasErrors(['last_name']);
    }

    public function test_mobile_special_characters_rejected(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->withSession(['register_security_answer' => 7])->post('/register', $this->validPayload([
            'mobile' => '+92-300-1234567',
            'security_answer' => '7',
        ]));

        $response->assertSessionHasErrors(['mobile']);
    }

    public function test_password_mismatch_rejected(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->withSession(['register_security_answer' => 7])->post('/register', $this->validPayload([
            'password_confirmation' => 'Different123!',
            'security_answer' => '7',
        ]));

        $response->assertSessionHasErrors(['password_confirmation']);
    }

    public function test_wrong_security_answer_rejected(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->withSession(['register_security_answer' => 9])->post('/register', $this->validPayload([
            'security_answer' => '5',
        ]));

        $response->assertSessionHasErrors(['security_answer']);
    }

    public function test_correct_signup_succeeds(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->withSession(['register_security_answer' => 7])->post('/register', $this->validPayload([
            'email' => 'new.signup@example.com',
            'security_answer' => '7',
        ]));

        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'new.signup@example.com',
            'account_type' => AccountType::Customer->value,
        ]);
    }

    public function test_ajax_validation_endpoint_returns_field_specific_errors(): void
    {
        User::factory()->create(['email' => 'exists@example.com']);
        $this->withoutMiddleware([ValidateCsrfToken::class]);

        $response = $this->withSession(['register_security_answer' => 7])->postJson('/register/customer/validate-field', [
            'field' => 'email',
            'email' => 'exists@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    private function validPayload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali.khan@example.com',
            'mobile' => '923001234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'security_answer' => '7',
            'terms' => '1',
        ], $override);
    }
}
