<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_without_email(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['username' => 'admin', 'email' => null, 'active' => true]);
        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_and_inactive_accounts_cannot_login(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['username' => 'admin', 'active' => true]);
        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin', 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);
        $this->assertGuest();

        $user->update(['active' => false]);
        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);
        $this->assertGuest();
    }
}
