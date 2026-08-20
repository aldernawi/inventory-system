<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationAndModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_user_can_log_in_and_reach_the_module_selector(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Admin, $user->fresh()->role);
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $response = $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'inactive@example.com',
                'password' => 'password',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_user_deactivated_after_login_loses_access_on_the_next_request(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_users_can_access_both_separated_module_dashboards(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('salami.dashboard'))
            ->assertOk()
            ->assertSee('إدارة مخزن السلامي')
            ->assertSee('المحلات');

        $this->actingAs($user)
            ->get(route('flowers.dashboard'))
            ->assertOk()
            ->assertSee('إدارة مخزون الورد')
            ->assertSee('خروج الورد')
            ->assertDontSee('المحلات');
    }

    public function test_there_is_no_public_registration_route(): void
    {
        $this->assertFalse(Route::has('register'));
    }
}
