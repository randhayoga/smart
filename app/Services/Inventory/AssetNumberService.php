<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;

/**
 * Service managing serialized asset (number) generation.
 *
 * Serial number increments are strictly scoped by Organizer (matching '-{OrganizerName}-PTRE'),
 * ensuring continuous serialization regardless of types or receipt years.
 */
class AssetNumberService
{
    /**
     * Retrieve the next sequential serial integer for a given organizer code.
     */
    public function getNextSerial(string $organizerCode): int
    {
        if (empty($organizerCode)) {
            return 1;
        }

        $assetNumbers = Asset::where('number', 'like', "%-{$organizerCode}-PTRE%")->pluck('number');

        $maxSerial = 0;
        foreach ($assetNumbers as $number) {
            $parts = explode('-', $number);
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $val = (int) $parts[0];
                if ($val > $maxSerial) {
                    $maxSerial = $val;
                }
            }
        }

        return $maxSerial + 1;
    }

    /**
     * Formulate a single unique asset code for a given Lot.
     */
    public function generateAssetNumber(Lot $lot, ?int $serial = null): string
    {
        $typeCode = $lot->model?->type?->code;
        $organizerCode = $lot->organizer?->name;

        if (!$typeCode || !$organizerCode) {
            return 'AST-' . str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }

        $combination = "{$typeCode}-{$organizerCode}-PTRE";
        $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');

        $currentSerial = $serial ?? $this->getNextSerial($organizerCode);

        do {
            $serialStr = str_pad((string)$currentSerial, 5, '0', STR_PAD_LEFT);
            $generated = "{$serialStr}-{$combination}-{$yy}";
            $exists = Asset::where('number', $generated)->exists();
            if ($exists) {
                $currentSerial++;
            }
        } while ($exists);

        return $generated;
    }

    /**
     * Formulate continuous bulk asset codes for a given Lot.
     *
     * @return array<int, string>
     */
    public function generateBulkAssetNumbers(Lot $lot, int $quantity, ?string $fallbackBaseNumber = null): array
    {
        $typeCode = $lot->model?->type?->code;
        $organizerCode = $lot->organizer?->name;

        if ($typeCode && $organizerCode) {
            $combination = "{$typeCode}-{$organizerCode}-PTRE";
            $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');

            $currentSerial = $this->getNextSerial($organizerCode);
            $generatedNumbers = [];

            for ($i = 0; $i < $quantity; $i++) {
                do {
                    $serialStr = str_pad((string)$currentSerial, 5, '0', STR_PAD_LEFT);
                    $num = "{$serialStr}-{$combination}-{$yy}";
                    $exists = Asset::where('number', $num)->exists() || in_array($num, $generatedNumbers, true);
                    if ($exists) {
                        $currentSerial++;
                    }
                } while ($exists);

                $generatedNumbers[] = $num;
                $currentSerial++;
            }

            return $generatedNumbers;
        }

        // Fallback for missing type/organizer
        $baseNumber = $fallbackBaseNumber ?? 'AST-00001';
        $suffixPos = strrpos($baseNumber, '-A');
        if ($suffixPos !== false) {
            $prefix = substr($baseNumber, 0, $suffixPos + 2);
            $startNumStr = substr($baseNumber, $suffixPos + 2);
            $startNum = (int)$startNumStr;
            $padLength = strlen($startNumStr);
        } else {
            $prefix = $baseNumber . '-';
            $startNum = 1;
            $padLength = 2;
        }

        $generatedNumbers = [];
        for ($i = 0; $i < $quantity; $i++) {
            $generatedNumbers[] = $prefix . str_pad((string)($startNum + $i), $padLength, '0', STR_PAD_LEFT);
        }

        return $generatedNumbers;
    }
}
