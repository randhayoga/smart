<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\HttpFoundation\Response;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Typography\FontFactory;
use Intervention\Image\Encoders\PngEncoder;

class UnitQrCodeController extends Controller
{
    /*
    /**
     * Display the QR code for the specified unit.
     * /
    public function show(Unit $unit): Response
    {
        $unit->loadMissing('lot.organizer');
        $unitCode = $unit->number;

        // 1. Generate QR Code image
        $qrBuilder = new Builder();
        $qrResult = $qrBuilder->build(
            data: $unitCode,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 116,
            margin: 0
        );
        $qrPngString = $qrResult->getString();

        // 2. Initialize Intervention Image Manager with GD Driver
        $manager = new ImageManager(new Driver());

        // 3. Create a white canvas (650x177)
        $canvas = $manager->createImage(600, 177);
        $canvas->setResolution(300, 300);
        $canvas->fill('ffffff');

        // 4. Place Company Logo
        $logoPath = public_path('assets/images/logoR3.png');
        if (file_exists($logoPath)) {
            $logo = $manager->decode($logoPath);
            $logo->scale(height: 100);
            $canvas->insert(
                $logo,
                20,
                5,
                'bottom-right'
            );
        }

        // 5. Place QR Code
        $qrImage = $manager->decode($qrPngString);
        $canvas->insert(
            $qrImage,
            25,
            1,
            'bottom-left'
        );

        // 6. Draw Company Name Text
        $fontPath = public_path('fonts/MirandaSans-SemiBold.ttf');
        $canvas->text('PT REKAYASA ENGINEERING', 25, 1, function (FontFactory $font) use ($fontPath) {
            if (file_exists($fontPath)) {
                $font->filename($fontPath);
            }
            $font->size(27);
            $font->color('000000');
            $font->align('left', 'top');
        });

        // 7. Draw Unit Code Text
        $canvas->text($unitCode, 25, 30, function (FontFactory $font) use ($fontPath) {
            if (file_exists($fontPath)) {
                $font->filename($fontPath);
            }
            $font->size(27);
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
    */

    /**
     * Display the QR code for the specified unit (with reversed logo and QR positions).
     */
    public function show(Unit $unit): Response
    {
        $unit->loadMissing('lot.organizer');
        $unitCode = $unit->number;
        $scanUrl = route('smart.inventory.units.scan', $unit->id);

        // 1. Generate QR Code image
        $qrBuilder = new Builder();
        $qrResult = $qrBuilder->build(
            data: $scanUrl,
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
        $canvas->text('PT REKAYASA ENGINEERING', 10, 1, function (FontFactory $font) use ($fontPath) {
            if (file_exists($fontPath)) {
                $font->filename($fontPath);
            }
            $font->size(26);
            $font->color('000000');
            $font->align('left', 'top');
        });

        // 7. Draw Unit Code Text
        $canvas->text($unitCode, 10, 29, function (FontFactory $font) use ($fontPath) {
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
