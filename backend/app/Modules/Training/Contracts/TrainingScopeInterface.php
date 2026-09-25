<?php
 
namespace App\Modules\Training\Contracts;
 
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface TrainingScopeInterface
{
    public function applyMine(Builder $query, User $user): Builder;
}