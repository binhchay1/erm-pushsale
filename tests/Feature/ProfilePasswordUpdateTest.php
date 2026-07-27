<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword1!'),
        ]);

        $response = $this->actingAs($user)->put('/profile', [
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
    }

    public function test_password_update_requires_confirmation_match(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword1!'),
        ]);

        $response = $this->actingAs($user)->from('/profile')->put('/profile', [
            'password' => 'NewPassword1!',
            'password_confirmation' => 'DifferentPassword1!',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('OldPassword1!', $user->fresh()->password));
    }

    public function test_profile_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Index')
                ->has('profile.name')
                ->has('profile.email'));
    }
}
