<?php

namespace App\Modules\Pph21\Enums;

enum PtkpStatus: string
{
    case Tk0 = 'tk0';
    case Tk1 = 'tk1';
    case Tk2 = 'tk2';
    case Tk3 = 'tk3';
    case K0 = 'k0';
    case K1 = 'k1';
    case K2 = 'k2';
    case K3 = 'k3';
    // K/I (gabung penghasilan suami-istri) belum diimplementasikan — future proof, tinggal tambah case.

    public function label(): string
    {
        return match ($this) {
            self::Tk0 => 'TK/0', self::Tk1 => 'TK/1', self::Tk2 => 'TK/2', self::Tk3 => 'TK/3',
            self::K0 => 'K/0', self::K1 => 'K/1', self::K2 => 'K/2', self::K3 => 'K/3',
        };
    }

    /**
     * Mapping status PTKP -> kategori TER, sesuai PMK 168/2023.
     * [Regulasi Pemerintah] TK/0, TK/1, K/0 -> A; TK/2, TK/3, K/1, K/2 -> B; K/3 -> C.
     */
    public function terCategory(): TerCategory
    {
        return match ($this) {
            self::Tk0, self::Tk1, self::K0 => TerCategory::A,
            self::Tk2, self::Tk3, self::K1, self::K2 => TerCategory::B,
            self::K3 => TerCategory::C,
        };
    }
}