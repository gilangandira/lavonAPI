<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'cluster_id',
        'price',
        'sale_date',
        // New fields
        'locked_type',
        'status',
        'booking_date',
        'payment_amount',
        'cicilan_count',
        'monthly_installment',
        'payment_method',
    ];

    protected $appends = ['payment_status_info'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getPaymentStatusInfoAttribute()
    {
        // 0. Check Booked/Deposited specifically
        if (in_array($this->status, ['Booked', 'Deposited'])) {
            return [
                'status' => $this->status,
                'label' => strtoupper($this->status),
                'color' => 'purple', // distinct color
                'next_due_date' => '-'
            ];
        }

        // 1. If fully paid
        if ($this->status === 'Paid' || $this->payment_amount >= $this->price) {
            return [
                'status' => 'Paid',
                'label' => 'LUNAS',
                'color' => 'green',
                'next_due_date' => '-'
            ];
        }

        // 2. Calculate months paid
        $monthly = $this->monthly_installment > 0 ? $this->monthly_installment : 1;
        // avoid division by zero
        $monthsPaid = floor(($this->payment_amount) / $monthly);
        
        // 3. Calculate Next Due Date
        // Initial agreement date + months already paid + 1 month (next one)
        if ($this->sale_date) {
            $startDate = \Carbon\Carbon::parse($this->sale_date);
            $nextDueDate = $startDate->copy()->addMonths($monthsPaid + 1);
        } else {
            // Fallback if sale_date missing
            return [
                'status' => 'Unknown',
                'label' => 'No Date',
                'color' => 'gray',
                'next_due_date' => '-'
            ];
        }

        // 4. Check Deadline
        $today = \Carbon\Carbon::now();
        $diffDays = $today->diffInDays($nextDueDate, false);

        if ($diffDays < 0) {
            // Negative means nextDueDate is in the past -> OVERDUE
            return [
                'status' => 'Overdue',
                'label' => 'TELAT BAYAR (' . abs(round($diffDays)) . ' hari)',
                'color' => 'red',
                'next_due_date' => $nextDueDate->format('Y-m-d')
            ];
        } elseif ($diffDays <= 7) {
            // Due soon
            return [
                'status' => 'Due Soon',
                'label' => 'Jatuh Tempo ' . round($diffDays) . ' hari lagi',
                'color' => 'yellow',
                'next_due_date' => $nextDueDate->format('Y-m-d')
            ];
        } else {
            // Normal
            return [
                'status' => 'Normal',
                'label' => 'Lancar',
                'color' => 'blue',
                'next_due_date' => $nextDueDate->format('Y-m-d')
            ];
        }
    }
}


