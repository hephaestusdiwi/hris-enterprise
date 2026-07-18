<?php

namespace App\Modules\Company\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Requests\StoreCompanyRequest;
use App\Modules\Company\Requests\UpdateCompanyRequest;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $companies,
        ]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $company = Company::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Company berhasil dibuat',
            'data' => $company,
        ], 201);
    }

    public function show(Company $company)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $company,
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $company->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Company berhasil diperbarui',
            'data' => $company,
        ]);
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company berhasil dihapus',
            'data' => null,
        ]);
    }
}