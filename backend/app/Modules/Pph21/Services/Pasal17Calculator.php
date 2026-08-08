<?php

namespace App\Modules\Pph21\Services;

use App\Modules\Pph21\Contracts\TaxBracketResolverInterface;
use App\Modules\Pph21\Support\Pph21Math;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Helper murni buat jalanin tarif Pasal 17 progresif berlapis atas suatu PKP.
 * Dipisah dari TaxCalculationEngine supaya bisa dipakai ulang oleh perhitungan
 * gross-up (yang butuh panggil fungsi pajak berkali-kali buat konvergensi).
 */
class Pasal17Calculator
{
    public function __construct(private TaxBracketResolverInterface $bracketResolver)
    {
    }

    /**
     * @param  Collection<int, \App\Modules\Pph21\Models\TaxBracketConfig>|null  $brackets
     *         Opsional — kalau sudah di-resolve sebelumnya (mis. dipanggil berkali-kali
     *         dalam loop konvergensi gross-up), bisa dikirim langsung biar ga query berkali-kali.
     */
    public function calculate(string $pkp, Carbon $referenceDate, ?Collection $brackets = null): string
    {
        $brackets = $brackets ?? $this->bracketResolver->resolveActiveBrackets($referenceDate);

        $remaining = $pkp;
        $totalTax = '0.00';

        foreach ($brackets as $bracket) {
            if (Pph21Math::gt('0', $remaining)) {
                break;
            }

            $layerCeiling = $bracket->income_to !== null
                ? Pph21Math::sub((string) $bracket->income_to, (string) $bracket->income_from)
                : $remaining;

            $taxableInLayer = Pph21Math::min($remaining, $layerCeiling);

            if (Pph21Math::gt('0', $taxableInLayer)) {
                break;
            }

            $taxInLayer = Pph21Math::mul($taxableInLayer, Pph21Math::div((string) $bracket->rate_percentage, '100', 6));
            $totalTax = Pph21Math::add($totalTax, $taxInLayer);
            $remaining = Pph21Math::sub($remaining, $taxableInLayer);
        }

        return $totalTax;
    }
}