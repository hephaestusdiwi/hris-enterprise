<?php

use App\Modules\Reimbursement\Controllers\ReimbursementApprovalController;
use App\Modules\Reimbursement\Controllers\ReimbursementBalanceController;
use App\Modules\Reimbursement\Controllers\ReimbursementBenefitController;
use App\Modules\Reimbursement\Controllers\ReimbursementController;
use App\Modules\Reimbursement\Controllers\ReimbursementPolicyController;
use Illuminate\Support\Facades\Route;

// Self-service employee

Route::get(
    '/my-reimbursement-balances',
    [ReimbursementController::class, 'myBalances']
);

Route::get(
    '/my-reimbursements',
    [ReimbursementController::class, 'myReimbursements']
);

Route::get(
    '/my-reimbursements/{reimbursement}',
    [ReimbursementController::class, 'myReimbursementShow']
);

Route::middleware('permission:create reimbursements')
    ->post(
        '/my-reimbursements',
        [ReimbursementController::class, 'store']
    );


// Policy + Benefit + Balance Management

Route::middleware('permission:manage reimbursement policies')
    ->group(function () {

        Route::get(
            '/reimbursement-policies',
            [ReimbursementPolicyController::class, 'index']
        );

        Route::get(
            '/reimbursement-policies/{reimbursementPolicy}',
            [ReimbursementPolicyController::class, 'show']
        );

        Route::post(
            '/reimbursement-policies',
            [ReimbursementPolicyController::class, 'store']
        );

        Route::put(
            '/reimbursement-policies/{reimbursementPolicy}',
            [ReimbursementPolicyController::class, 'update']
        );

        Route::get(
            '/reimbursement-policies/{reimbursementPolicy}/benefits',
            [ReimbursementBenefitController::class, 'index']
        );

        Route::post(
            '/reimbursement-policies/{reimbursementPolicy}/benefits',
            [ReimbursementBenefitController::class, 'store']
        );

        Route::put(
            '/reimbursement-benefits/{reimbursementBenefit}',
            [ReimbursementBenefitController::class, 'update']
        );

        Route::get(
            '/reimbursement-balances',
            [ReimbursementBalanceController::class, 'index']
        );

        Route::post(
            '/reimbursement-balances',
            [ReimbursementBalanceController::class, 'store']
        );

        Route::post(
            '/reimbursement-balances/{reimbursementBalance}/stop',
            [ReimbursementBalanceController::class, 'stop']
        );
    });


// HR / Finance Reimbursement

Route::middleware('permission:view reimbursements')
    ->group(function () {

        Route::get(
            '/reimbursements',
            [ReimbursementController::class, 'index']
        );

        Route::get(
            '/reimbursements/{reimbursement}',
            [ReimbursementController::class, 'show']
        );
    });

Route::middleware('permission:cancel reimbursements')
    ->post(
        '/reimbursements/{reimbursement}/cancel',
        [ReimbursementController::class, 'cancel']
    );

Route::middleware('permission:disburse reimbursements')
    ->post(
        '/reimbursements/{reimbursement}/disburse',
        [ReimbursementController::class, 'disburse']
    );


// Approval

Route::get(
    '/reimbursement-approvals',
    [ReimbursementApprovalController::class, 'index']
);

Route::post(
    '/reimbursement-approvals/{decision}/decide',
    [ReimbursementApprovalController::class, 'decide']
);