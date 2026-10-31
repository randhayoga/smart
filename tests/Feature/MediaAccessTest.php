<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_media(): void
    {
        $response = $this->get('/media/request_forms/sample.pdf');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_request_forms_media(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('request_forms/sample.pdf', 'dummy content');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/media/request_forms/sample.pdf');
        $response->assertOk();
        $this->assertEquals('dummy content', $response->streamedContent());
    }

    public function test_authenticated_user_can_access_returns_media(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('returns/proof.jpg', 'proof image content');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/media/returns/proof.jpg');
        $response->assertOk();
        $this->assertEquals('proof image content', $response->streamedContent());
    }

    public function test_unauthorized_media_directory_is_forbidden(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('secret_passwords/passwords.txt', 'secret');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/media/secret_passwords/passwords.txt');
        $response->assertStatus(403);
    }

    public function test_directory_traversal_is_blocked(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/media/request_forms/../etc/passwd');
        $response->assertStatus(400);
    }
}
