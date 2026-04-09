<?php

namespace App\Models;

use CodeIgniter\Model;

class SubscriptionModel extends Model
{
    protected $table = 'subscriptions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'cliente_id',
        'plan_id',
        'status',
        'gateway',
        'gateway_subscription_id',
        'started_at',
        'next_billing_at',
        'ends_at',
    ];
    protected $useTimestamps = true;
}
