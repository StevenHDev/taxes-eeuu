<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_api_tokens_page_is_displayed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('api-tokens.index'))
            ->assertOk();
    }

    public function test_a_user_can_create_an_api_token_with_selected_abilities()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('api-tokens.store'), [
                'name' => 'Integración contable',
                'abilities' => ['tax-documents:read', 'tax-documents:write'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('api-tokens.index'));

        $token = $user->tokens()->first();

        $this->assertSame('Integración contable', $token->name);
        $this->assertSame(['tax-documents:read', 'tax-documents:write'], $token->abilities);
    }

    public function test_a_user_can_revoke_their_own_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('to-revoke');

        $this->actingAs($user)
            ->delete(route('api-tokens.destroy', $token->accessToken->id))
            ->assertRedirect(route('api-tokens.index'));

        $this->assertModelMissing($token->accessToken);
    }

    public function test_a_user_cannot_revoke_another_users_token()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $token = $owner->createToken('owned-by-someone-else');

        $this->actingAs($intruder)
            ->delete(route('api-tokens.destroy', $token->accessToken->id))
            ->assertNotFound();

        $this->assertModelExists($token->accessToken);
    }
}
