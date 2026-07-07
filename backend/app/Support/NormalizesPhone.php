<?php
namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

trait NormalizesPhone
{
    protected function normalizePhone(?string $raw, string $countryHint = 'SY'): ?array
    {
        if ($raw === null || trim($raw) === '') return null;
        try {
            $util   = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($raw, $countryHint);
            if (! $util->isValidNumber($parsed)) return null;
            return [
                'e164'    => $util->format($parsed, PhoneNumberFormat::E164),
                'country' => $util->getRegionCodeForNumber($parsed),
            ];
        } catch (NumberParseException) {
            return null;
        }
    }
}
