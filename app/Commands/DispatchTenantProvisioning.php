<?php

namespace App\Commands;

use App\Services\TenantProvisioningService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DispatchTenantProvisioning extends BaseCommand
{
    protected $group = 'Custom';
    protected $name = 'tenants:dispatch-pending';
    protected $description = 'Reenvia jobs pendentes/falhos de provisionamento para a automação.';

    public function run(array $params)
    {
        $limit = isset($params[0]) ? max(1, (int) $params[0]) : 20;
        $count = (new TenantProvisioningService())->dispatchPending($limit);

        CLI::write("Jobs despachados: {$count}", 'green');
    }
}
