<?php

use App\Modules\EmployeeSalary\Controllers\EmployeeSalaryController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:view employee salaries')->group(function () {
    Route::get('/employee-salaries', [EmployeeSalaryController::class, 'index']);
    Route::get('/employee-salaries/{employeeSalary}', [EmployeeSalaryController::class, 'show']);
    Route::post('employees/{employee}/resolved-salary/preview', [EmployeeSalaryController::class, 'preview']);
    Route::get('/employees/{employee}/resolved-salary', [EmployeeSalaryController::class, 'resolved']);
});

Route::middleware('permission:create employee salaries')->post('/employee-salaries', [EmployeeSalaryController::class, 'store']);
Route::middleware('permission:delete employee salaries')->delete('/employee-salaries/{employeeSalary}', [EmployeeSalaryController::class, 'destroy']);