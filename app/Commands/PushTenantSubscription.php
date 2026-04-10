<?php

namespace App\Commands;

use App\Services\TenantProvisioningService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PushTenantSubscription extends BaseCommand
{
    protected $group = 'Custom';
    protected $name = 'tenants:push-subscription';
    protected $description = 'Reenvia tenant_subscription ao provisionador (sync_subscription_only) para atualizar a tabela subscriptions no portal do tenant.';
    protected $usage = 'tenants:push-subscription <cliente_id>';
    protected $arguments = [
        'cliente_id' => 'ID do cliente (usa tenant_db_name ou requested_db_name do job se necessário).',
    ];

    public function run(array $params)
    {
        $id = isset($params[0]) ? (int) $params[0] : 0;
        if ($id < 1) {
            CLI::error('Uso: php spark tenants:push-subscription <cliente_id>');

            return;
        }

        $result = (new TenantProvisioningService())->dispatchSubscriptionSync($id);

        if ($result['success']) {
            CLI::write($result['message'], 'green');
            $decoded = json_decode($result['response_body'], true);
            if (is_array($decoded) && ! empty($decoded['log'])) {
                CLI::write('Log do provisionador:', 'cyan');
                CLI::write((string) $decoded['log']);
            }
        } else {
            CLI::write($result['message'], 'red');
            if ($result['http_code'] > 0) {
                CLI::write('HTTP ' . $result['http_code'], 'yellow');
            }
            if ($result['response_body'] !== '') {
                CLI::write('Corpo da resposta:', 'yellow');
                CLI::write($result['response_body']);
            }
        }
    }
}
