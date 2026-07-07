<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

// P0 stub — expanded in P1
class Guest extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuid;

    protected $fillable = ['uuid', 'name'];
}
