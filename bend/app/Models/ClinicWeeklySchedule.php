<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicWeeklySchedule extends Model
{
    protected $fillable = [
        'day_of_week',
        'is_open',
        'open_time',
        'close_time',
        'dentist_count',
        'max_per_slot',
      ];
      
    
}
