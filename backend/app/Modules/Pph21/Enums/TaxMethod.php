<?php

namespace App\Modules\Pph21\Enums;

enum TaxMethod: string
{
    case Gross = 'gross';
    case GrossUp = 'gross_up';
    case Netto = 'netto';
    // case Mix = 'mix' // future proof

    public function label(): string
    {
        return match ($this) {
            self::Gross => 'Gross',
            self::GrossUp => 'Gross-Up',
            self::Netto => 'Netto',
        };
    }
}