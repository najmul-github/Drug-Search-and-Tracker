<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDrug extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'rxcui',
        'name',
        'base_names',
        'dose_forms',
    ];

    protected $casts = [
        'base_names' => 'array',
        'dose_forms' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
