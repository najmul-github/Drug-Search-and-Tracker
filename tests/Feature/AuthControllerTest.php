<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_register_and_get_token()
    {
        $payload = [
            'name' => 'Jane Tester',
            'email' => 'jane@example.com',
            'password' => 'secret123'
        ];

        $res = $this->postJson('/api/register', $payload);
        $res->assertStatus(201)
            ->assertJsonStructure(['access_token','token_type']);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    /** @test */
    public function user_can_login_and_get_token()
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
        ]);

        $res = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123'
        ]);

        $res->assertOk()->assertJsonStructure(['access_token','token_type']);
    }

    /** @test */
    public function login_fails_with_bad_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
        ]);

        $res = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-pass'
        ]);

        $res->assertStatus(422);
    }

    /** @test */
    public function user_can_logout()
    {
        $user = User::factory()->create();
        // login to get token
        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);
        $token = $login->json('access_token');

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $res->assertOk()->assertJson(['message' => 'Logged out']);
    }
}
