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
        'cliente_id' => 'ID do cliente (exige tenant_db_name preenchido após provisionamento).',
    ];

    public function run(array $params)
    {
        $id = isset($params[0]) ? (int) $params[0] : 0;
        if ($id < 1) {
            CLI::error('Uso: php spark tenants:push-subscription <cliente_id>');

            return;
        }

        $ok = (new TenantProvisioningService())->dispatchSubscriptionSync($id);
        if ($ok) {
            CLI::write("Sync de assinatura enviado para o cliente {$id}.", 'green');
        } else {
            CLI::write('Falha ao enviar (confira provisioning.dispatchUrl, token, tenant_db_name do cliente e o handler sync no provisionador).', 'red');
        }
    }
}
