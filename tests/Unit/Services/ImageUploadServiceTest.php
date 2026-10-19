<?php

namespace Tests\Unit\Services;

use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    private ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = new ImageUploadService();
    }

    public function test_converts_jpeg_image_to_webp_and_stores(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $path = $this->service->storeAsWebp($file, 'inventory', 'local');

        $this->assertStringStartsWith('inventory/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('local')->assertExists($path);

        $content = Storage::disk('local')->get($path);
        $info = @getimagesizefromstring($content);
        $this->assertEquals('image/webp', $info['mime'] ?? null);
    }

    public function test_converts_png_image_with_alpha_to_webp_and_stores(): void
    {
        $file = UploadedFile::fake()->image('transparent.png', 50, 50);

        $path = $this->service->storeAsWebp($file, 'inventory', 'local');

        $this->assertStringStartsWith('inventory/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('local')->assertExists($path);

        $content = Storage::disk('local')->get($path);
        $info = @getimagesizefromstring($content);
        $this->assertEquals('image/webp', $info['mime'] ?? null);
    }

    public function test_converts_native_webp_upload_and_stores(): void
    {
        // Generate real webp data
        $gd = imagecreatetruecolor(20, 20);
        ob_start();
        imagewebp($gd);
        $webpData = ob_get_clean();
        imagedestroy($gd);

        $file = UploadedFile::fake()->createWithContent('image.webp', $webpData);

        $path = $this->service->storeAsWebp($file, 'inventory', 'local');

        $this->assertStringStartsWith('inventory/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_store_image_or_file_stores_pdf_as_original_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $path = $this->service->storeImageOrFile($file, 'memos', 'local');

        $this->assertStringStartsWith('memos/', $path);
        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_store_image_or_file_converts_image_to_webp(): void
    {
        $file = UploadedFile::fake()->image('memo-photo.png', 80, 80);

        $path = $this->service->storeImageOrFile($file, 'memos', 'local');

        $this->assertStringStartsWith('memos/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('local')->assertExists($path);
    }
}
