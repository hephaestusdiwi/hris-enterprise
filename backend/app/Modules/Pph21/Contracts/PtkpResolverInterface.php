<?php

namespace App\Modules\Pph21\Contracts;

use App\Modules\Pph21\Enums\PtkpStatus;
use App\Modules\Pph21\Models\PtkpConfig;
use Carbon\Carbon;

interface PtkpResolverInterface
{
    public function resolveActiveVersion(PtkpStatus $status, Carbon $referenceDate): ?PtkpConfig;
}