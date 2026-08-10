<?php

namespace App\Modules\Payroll\DataTransferObjects;

use App\Modules\Payroll\Enums\PayslipLineSource;
use App\Modules\Payroll\Enums\PayslipLineType;

final class PayslipLineDraft
{
    public function __construct(
        public readonly PayslipLineType $type,
        public readonly PayslipLineSource $source,
        public readonly string $label,
        public readonly string $amount,
        public readonly ?int $referenceId = null,
    ) {
    }
}