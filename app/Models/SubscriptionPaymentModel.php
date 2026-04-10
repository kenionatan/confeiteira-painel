<?php

namespace App\Models;

use CodeIgniter\Model;

class SubscriptionPaymentModel extends Model
{
    protected $table            = 'subscription_payments';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'subscription_id',
        'amount',
        'currency',
        'status',
        'period_start',
        'period_end',
        'gateway',
        'gateway_invoice_id',
        'description',
        'paid_at',
    ];
    protected $useTimestamps = true;
}
