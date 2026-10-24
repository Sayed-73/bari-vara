<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageRouteTest extends TestCase
{
    public function test_serves_existing_file_from_public_storage(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('test-folder/sample.txt', 'Hello Public Storage');

        $response = $this->get('/storage/test-folder/sample.txt');

        $response->assertOk();
        $this->assertSame('Hello Public Storage', $response->streamedContent());
    }

    public function test_returns_404_for_non_existent_file(): void
    {
        Storage::fake('public');

        $response = $this->get('/storage/test-folder/not-found.txt');

        $response->assertNotFound();
    }
}
