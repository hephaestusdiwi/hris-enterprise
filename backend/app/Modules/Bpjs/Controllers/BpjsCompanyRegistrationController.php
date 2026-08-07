<?php

namespace App\Modules\Bpjs\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bpjs\Models\BpjsCompanyRegistration;
use App\Modules\Bpjs\Requests\StoreBpjsCompanyRegistrationRequest;
use Illuminate\Http\Request;

class BpjsCompanyRegistrationController extends Controller
{
    public function index(Request $request)
    {
        $registrations = BpjsCompanyRegistration::with('branch')
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->orderBy('npp_number')
            ->orderByDesc('effective_date')
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $registrations]);
    }

    public function store(StoreBpjsCompanyRegistrationRequest $request)
    {
        $registration = BpjsCompanyRegistration::create($request->validated());

        return response()->json(['success' => true, 'message' => 'Registrasi NPP berhasil ditambahkan', 'data' => $registration->load('branch')], 201);
    }

    public function destroy(BpjsCompanyRegistration $bpjsCompanyRegistration)
    {
        if ($bpjsCompanyRegistration->effective_date->isPast() || $bpjsCompanyRegistration->effective_date->isToday()) {
            return response()->json([
                'success' => false,
                'message' => 'Registrasi yang sudah berlaku tidak bisa dihapus.',
                'data' => null,
            ], 422);
        }

        $bpjsCompanyRegistration->delete();

        return response()->json(['success' => true, 'message' => 'Registrasi berhasil dihapus', 'data' => null]);
    }
}