<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'nik',
        'name',
        'phone',
        'address',
        'email',
        'criteria',
        'cicilan',
        'cluster_id',
        'payment_method',
    ];

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}

