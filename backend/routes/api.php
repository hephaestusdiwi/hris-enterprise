<?php

use App\Http\Controllers\Auth\AccountActivationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Publik — tidak butuh login, employee baru buka ini dari invite link
Route::post('/account-activation/validate', [AccountActivationController::class, 'validateToken']);
Route::post('/account-activation/complete', [AccountActivationController::class, 'complete']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    Route::middleware('permission:view dashboard')->get('/dashboard', [DashboardController::class, 'index']);

    Route::middleware('permission:view users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/roles', [UserController::class, 'roles']);
        Route::get('/users/{user}', [UserController::class, 'show']);
    });

    Route::middleware('permission:create users')->post('/users', [UserController::class, 'store']);
    Route::middleware('permission:edit users')->put('/users/{user}', [UserController::class, 'update']);
    Route::middleware('permission:delete users')->delete('/users/{user}', [UserController::class, 'destroy']);

    require __DIR__.'/../app/Modules/Company/Routes/api.php';
    require __DIR__.'/../app/Modules/Branch/Routes/api.php';
    require __DIR__.'/../app/Modules/Department/Routes/api.php';
    require __DIR__.'/../app/Modules/Position/Routes/api.php';
    require __DIR__.'/../app/Modules/EmploymentStatus/Routes/api.php';
    require __DIR__.'/../app/Modules/EmploymentType/Routes/api.php';
    require __DIR__.'/../app/Modules/Employee/Routes/api.php';
    require __DIR__.'/../app/Modules/Holiday/Routes/api.php';
    require __DIR__.'/../app/Modules/JobLevel/Routes/api.php';
    require __DIR__.'/../app/Modules/Shift/Routes/api.php';
    require __DIR__.'/../app/Modules/WorkingSchedule/Routes/api.php';
    require __DIR__.'/../app/Modules/AttendanceSetting/Routes/api.php';
    require __DIR__.'/../app/Modules/ApprovalFlow/Routes/api.php';
    require __DIR__.'/../app/Modules/Attendance/Routes/api.php';
    require __DIR__.'/../app/Modules/AttendanceRequest/Routes/api.php';
    require __DIR__.'/../app/Modules/LeaveType/Routes/api.php';
    require __DIR__.'/../app/Modules/LeaveBalance/Routes/api.php';
    require __DIR__.'/../app/Modules/LeaveRequest/Routes/api.php';
    require __DIR__.'/../app/Modules/SalaryComponent/Routes/api.php';
    require __DIR__.'/../app/Modules/SalaryStructure/Routes/api.php';
    require __DIR__.'/../app/Modules/EmployeeSalary/Routes/api.php';
    require __DIR__.'/../app/Modules/EmployeeAllowance/Routes/api.php';
    require __DIR__.'/../app/Modules/EmployeeDeduction/Routes/api.php';
    require __DIR__.'/../app/Modules/Loan/Routes/api.php';
    require __DIR__.'/../app/Modules/Reimbursement/Routes/api.php';
    require __DIR__.'/../app/Modules/Bpjs/Routes/api.php';
    require __DIR__.'/../app/Modules/Pph21/Routes/api.php';
    require __DIR__.'/../app/Modules/Payroll/Routes/api.php';
    require __DIR__.'/../app/Modules/HiringRequisition/Routes/api.php';
    require __DIR__.'/../app/Modules/JobVacancy/Routes/api.php';
    require __DIR__.'/../app/Modules/EmployeeMovement/Routes/api.php';
    require __DIR__.'/../app/Modules/Candidate/Routes/api.php';
    require __DIR__.'/../app/Modules/Screening/Routes/api.php';
    require __DIR__.'/../app/Modules/Interview/Routes/api.php';
    require __DIR__.'/../app/Modules/Offering/Routes/api.php';
    require __DIR__.'/../app/Modules/ContractProbationSetting/Routes/api.php';
    require __DIR__.'/../app/Modules/Announcement/Routes/api.php';
    Route::get('/attendance-approvals', [\App\Modules\Attendance\Controllers\AttendanceApprovalController::class, 'index']);
    Route::post('/attendance-approvals/{decision}/decide', [\App\Modules\Attendance\Controllers\AttendanceApprovalController::class, 'decide']);
});

require __DIR__.'/../app/Modules/Attendance/Routes/device.php';