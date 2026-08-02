<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_path_serves_public_disk_file_without_symlink(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('branding/logo.png', $png);

        $this->get('/storage/branding/logo.png')
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_storage_path_rejects_traversal(): void
    {
        $this->get('/storage/../.env')->assertNotFound();
        $this->get('/storage/branding/../../.env')->assertNotFound();
    }

    public function test_missing_storage_file_is_not_found(): void
    {
        $this->get('/storage/branding/missing.png')->assertNotFound();
    }
}
