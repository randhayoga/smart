<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\Result\GdResult;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asset QR Code Controller generating branded high-resolution QR code labels for physical assets.
 */
class AssetQrCodeController extends Controller
{
    /**
     * Display the QR code for the specified asset.
     */
    public function show(Asset $asset): Response
    {
        $assetCode = $asset->number;

        // 1. Generate QR Code containing only the Asset Code/Number
        $qrBuilder = new Builder();
        /** @var GdResult $qrResult */
        $qrResult = $qrBuilder->build(
            data: $assetCode,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 175,
            margin: 0
        );

        // 2. Initialize Intervention Image Manager with GD Driver
        $manager = new ImageManager(new Driver());

        // 3. Create a white canvas (600x177)
        $canvas = $manager->createImage(600, 177);
        $canvas->setResolution(300, 300);
        $canvas->fill('ffffff');

        // 4. Place Company Logo (placed at bottom-left)
        $logoPath = public_path('assets/images/logoR3.png');
        if (file_exists($logoPath)) {
            $logo = $manager->decode($logoPath);
            $logo->scale(height: 110);
            $canvas->insert(
                $logo,
                10,
                1,
                'bottom-left'
            );
        }

        // 5. Place QR Code directly from in-memory GD resource
        $canvas->insert(
            $qrResult->getImage(),
            1,
            1,
            'bottom-right'
        );

        // 6. Draw Company Name Text
        $fontPath = public_path('fonts/MirandaSans-SemiBold.ttf');
        $canvas->text('PT REKAYASA ENGINEERING', 10, 5, function (FontFactory $font) use ($fontPath) {
            if (file_exists($fontPath)) {
                $font->filename($fontPath);
            }
            $font->size(26);
            $font->color('000000');
            $font->align('left', 'top');
        });

        // 7. Draw Asset Number Text
        $canvas->text($assetCode, 10, 34, function (FontFactory $font) use ($fontPath) {
            if (file_exists($fontPath)) {
                $font->filename($fontPath);
            }
            $font->size(26);
            $font->color('000000');
            $font->align('left', 'top');
        });

        // 8. Encode to PNG
        $encoded = $canvas->encode(new PngEncoder());

        return response((string) $encoded, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="QR_' . $assetCode . '.png"',
        ]);
    }
}
