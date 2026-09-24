<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;

/**
 * Service managing serialized asset unit (number) generation.
 *
 * Serial number increments are strictly scoped by Organizer (matching '-{OrganizerName}-PTRE'),
 * ensuring continuous serialization regardless of subcategories or receipt years.
 */
class UnitNumberService
{
    /**
     * Retrieve the next sequential serial integer for a given organizer code.
     */
    public function getNextSerial(string $organizerCode): int
    {
        if (empty($organizerCode)) {
            return 1;
        }

        $unitNumbers = Unit::where('number', 'like', "%-{$organizerCode}-PTRE%")->pluck('number');

        $maxSerial = 0;
        foreach ($unitNumbers as $number) {
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
     * Formulate a single unique unit code for a given Lot.
     */
    public function generateUnitNumber(Lot $lot, ?int $serial = null): string
    {
        $subcategoryCode = $lot->barang?->subcategory?->code;
        $organizerCode = $lot->organizer?->name;

        if (!$subcategoryCode || !$organizerCode) {
            return 'UNT-' . str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }

        $combination = "{$subcategoryCode}-{$organizerCode}-PTRE";
        $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');

        $currentSerial = $serial ?? $this->getNextSerial($organizerCode);

        do {
            $serialStr = str_pad((string)$currentSerial, 5, '0', STR_PAD_LEFT);
            $generated = "{$serialStr}-{$combination}-{$yy}";
            $exists = Unit::where('number', $generated)->exists();
            if ($exists) {
                $currentSerial++;
            }
        } while ($exists);

        return $generated;
    }

    /**
     * Formulate continuous bulk unit codes for a given Lot.
     *
     * @return array<int, string>
     */
    public function generateBulkUnitNumbers(Lot $lot, int $quantity, ?string $fallbackBaseNumber = null): array
    {
        $subcategoryCode = $lot->barang?->subcategory?->code;
        $organizerCode = $lot->organizer?->name;

        if ($subcategoryCode && $organizerCode) {
            $combination = "{$subcategoryCode}-{$organizerCode}-PTRE";
            $yy = $lot->date_of_receipt ? $lot->date_of_receipt->format('y') : date('y');

            $currentSerial = $this->getNextSerial($organizerCode);
            $generatedNumbers = [];

            for ($i = 0; $i < $quantity; $i++) {
                do {
                    $serialStr = str_pad((string)$currentSerial, 5, '0', STR_PAD_LEFT);
                    $num = "{$serialStr}-{$combination}-{$yy}";
                    $exists = Unit::where('number', $num)->exists() || in_array($num, $generatedNumbers, true);
                    if ($exists) {
                        $currentSerial++;
                    }
                } while ($exists);

                $generatedNumbers[] = $num;
                $currentSerial++;
            }

            return $generatedNumbers;
        }

        // Fallback for missing subcategory/organizer
        $baseNumber = $fallbackBaseNumber ?? 'UNT-00001';
        $suffixPos = strrpos($baseNumber, '-U');
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
