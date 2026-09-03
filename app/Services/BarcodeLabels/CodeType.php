<?php

namespace App\Services\BarcodeLabels;

final class CodeType
{
    public const CODE128 = 'code128';

    public const QR = 'qr';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::CODE128, self::QR];
    }
}
