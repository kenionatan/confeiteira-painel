<?php

namespace App\Models;

use CodeIgniter\Model;

class TenantProvisionJobModel extends Model
{
    protected $table            = 'tenant_provision_jobs';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'cliente_id',
        'requested_host',
        'requested_db_name',
        'status',
        'attempt_count',
        'last_error',
        'external_request_id',
        'payload_json',
        'dispatched_at',
        'completed_at',
    ];
    protected $useTimestamps = true;
}
