<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicCalendar extends Model
{
    protected $table = 'clinic_calendar';

    protected $fillable = [
        'date',
        'is_open',
        'open_time',
        'close_time',
        'dentist_count',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'is_open' => 'boolean',
    ];
}
