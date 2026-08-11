<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use Symfony\Component\HttpFoundation\Response;


class UnitQrCodeController extends Controller
{
    /**
     * Display the QR code for the specified unit
     */
    public function show(Unit $unit): Response
    {
        $unit->loadMissing('lot.organizer');
        $unitCode = $unit->number;
        $scanPath = route('smart.scan', $unit, false);

        // 1. Generate QR Code image
        $qrBuilder = new Builder();
        $qrResult = $qrBuilder->build(
            data: $scanPath,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 175,
            margin: 0
        );
        $qrPngString = $qrResult->getString();

        // 2. Initialize Intervention Image Manager with GD Driver
        $manager = new ImageManager(new Driver());

        // 3. Create a white canvas (650x177)
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

        // 5. Place QR Code (placed at bottom-right)
        $qrImage = $manager->decode($qrPngString);
        $canvas->insert(
            $qrImage,
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

        // 7. Draw Unit Code Text
        $canvas->text($unitCode, 10, 34, function (FontFactory $font) use ($fontPath) {
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
            'Content-Disposition' => 'attachment; filename="QR_' . $unitCode . '.png"',
        ]);
    }
}
