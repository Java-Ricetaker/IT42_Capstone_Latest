<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'appointment_id',
        'patient_visit_id',
        'currency',
        'amount_due',
        'amount_paid',
        'method',
        'status',
        'reference_no',
        'maya_checkout_id',
        'maya_payment_id',
        'rrn',
        'auth_code',
        'redirect_url',
        'paid_at',
        'cancelled_at',
        'expires_at',
        'webhook_first_received_at',
        'webhook_last_payload',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'webhook_first_received_at' => 'datetime',
        'webhook_last_payload' => 'array',
        'meta' => 'array',
    ];

    // Relationships
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patientVisit()
    {
        return $this->belongsTo(PatientVisit::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeAwaiting($q)
    {
        return $q->where('status', 'awaiting_payment');
    }
    public function scopePaid($q)
    {
        return $q->where('status', 'paid');
    }
    public function scopeUnpaid($q)
    {
        return $q->where('status', 'unpaid');
    }
}