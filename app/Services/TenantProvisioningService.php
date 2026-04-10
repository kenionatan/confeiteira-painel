<?php

namespace App\Services;

use App\Models\ClienteModel;
use App\Models\PlanModel;
use App\Models\SubscriptionModel;
use App\Models\TenantProvisionJobModel;

class TenantProvisioningService
{
    /**
     * Agenda (ou re-agenda) provisionamento do tenant e tenta despachar para o provisionador.
     */
    public function queueForCliente(array $cliente): int
    {
        $host = strtolower(trim((string) ($cliente['dominio'] ?? '')));
        if ($host === '') {
            throw new \InvalidArgumentException('Cliente sem dominio para provisionamento.');
        }

        $jobModel = new TenantProvisionJobModel();
        $clienteModel = new ClienteModel();

        $job = $jobModel->where('cliente_id', (int) $cliente['id'])->first();
        $subdomain = $this->extractSubdomain($host);
        $data = [
            'cliente_id' => (int) $cliente['id'],
            'requested_host' => $host,
            'requested_db_name' => $this->buildDbName($subdomain),
            'status' => 'pending',
            'last_error' => null,
            'completed_at' => null,
        ];

        if ($job) {
            $jobModel->update((int) $job['id'], $data);
            $jobId = (int) $job['id'];
        } else {
            $jobId = (int) $jobModel->insert($data, true);
        }

        $clienteModel->update((int) $cliente['id'], [
            'tenant_status' => 'pending',
            'tenant_error_message' => null,
            'tenant_ready_at' => null,
        ]);

        $this->dispatchToAutomation($jobId);

        return $jobId;
    }

    public function dispatchPending(int $limit = 20): int
    {
        $jobModel = new TenantProvisionJobModel();
        $jobs = $jobModel
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('updated_at', 'ASC')
            ->findAll($limit);

        $count = 0;
        foreach ($jobs as $job) {
            $this->dispatchToAutomation((int) $job['id']);
            $count++;
        }

        return $count;
    }

    /**
     * Atualiza status pelo callback do provisionador.
     */
    public function applyCallback(array $payload): void
    {
        $clienteId = (int) ($payload['cliente_id'] ?? 0);
        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        if ($clienteId <= 0 || ! in_array($status, ['provisioning', 'ready', 'failed'], true)) {
            throw new \InvalidArgumentException('Payload de callback inválido.');
        }

        $jobModel = new TenantProvisionJobModel();
        $clienteModel = new ClienteModel();

        $job = $jobModel->where('cliente_id', $clienteId)->first();
        if (! $job) {
            throw new \RuntimeException('Job de provisionamento não encontrado.');
        }

        $now = date('Y-m-d H:i:s');
        $jobStatus = $status === 'ready' ? 'completed' : ($status === 'failed' ? 'failed' : 'processing');
        $error = isset($payload['error_message']) ? substr((string) $payload['error_message'], 0, 255) : null;

        $jobModel->update((int) $job['id'], [
            'status' => $jobStatus,
            'last_error' => $error,
            'completed_at' => $status === 'ready' ? $now : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $clienteUpdate = [
            'tenant_status' => $status,
            'tenant_error_message' => $error,
        ];
        if ($status === 'ready') {
            $clienteUpdate['tenant_ready_at'] = $now;
            if (! empty($payload['db_name'])) {
                $clienteUpdate['tenant_db_name'] = substr((string) $payload['db_name'], 0, 80);
            }
            if (! empty($payload['db_user'])) {
                $clienteUpdate['tenant_db_user'] = substr((string) $payload['db_user'], 0, 80);
            }
        }

        $clienteModel->update($clienteId, $clienteUpdate);
    }

    /**
     * Reenvia só os dados de assinatura para o provisionador (action sync_subscription_only).
     * Útil quando o tenant já existe mas subscriptions no portal ficou no seed "free".
     */
    public function dispatchSubscriptionSync(int $clienteId): bool
    {
        $cfg = config('Provisioning');
        if ($cfg->dispatchUrl === '') {
            return false;
        }

        $cliente = (new ClienteModel())->find($clienteId);
        if (! $cliente) {
            return false;
        }

        $dbName = trim((string) ($cliente['tenant_db_name'] ?? ''));
        if ($dbName === '') {
            return false;
        }

        $dominio = strtolower(trim((string) ($cliente['dominio'] ?? '')));
        try {
            $subdomain = $this->extractSubdomain($dominio);
        } catch (\Throwable) {
            return false;
        }

        $body = [
            'action' => 'sync_subscription_only',
            'cliente_id' => $clienteId,
            'requested_subdomain' => $subdomain,
            'requested_db_name' => $dbName,
            'tenant_subscription' => $this->buildTenantSubscriptionPayload($clienteId),
        ];

        $code = $this->postProvisionPayload($body, 30);

        return $code >= 200 && $code < 300;
    }

    private function dispatchToAutomation(int $jobId): void
    {
        $cfg = config('Provisioning');
        if ($cfg->dispatchUrl === '') {
            return;
        }

        $jobModel = new TenantProvisionJobModel();
        $job = $jobModel->find($jobId);
        if (! $job) {
            return;
        }

        $cliente = (new ClienteModel())->find((int) $job['cliente_id']);
        if (! $cliente) {
            return;
        }

        $requestedHost = (string) $job['requested_host'];
        $requestedSubdomain = $this->extractSubdomain($requestedHost);
        $appPath = rtrim((string) $cfg->portalBasePath, '/\\') . DIRECTORY_SEPARATOR . $requestedSubdomain;

        $body = [
            'provisioning_payload_version' => 1,
            'job_id' => (int) $job['id'],
            'cliente_id' => (int) $cliente['id'],
            'requested_host' => $requestedHost,
            'requested_subdomain' => $requestedSubdomain,
            'requested_db_name' => (string) ($job['requested_db_name'] ?? ''),
            'requested_app_path' => $appPath,
            'portal_git_repo' => (string) $cfg->portalGitRepo,
            'portal_git_ref' => (string) $cfg->portalGitRef,
            'tenant_admin_email' => strtolower(trim((string) ($cliente['email'] ?? ''))),
            'tenant_admin_name' => trim((string) ($cliente['nome'] ?? '')),
            'tenant_admin_password_hash' => (string) ($cliente['senha_hash'] ?? ''),
            'cliente' => [
                'nome' => (string) ($cliente['nome'] ?? ''),
                'email' => (string) ($cliente['email'] ?? ''),
                'whatsapp' => (string) ($cliente['whatsapp'] ?? ''),
                'dominio' => (string) ($cliente['dominio'] ?? ''),
            ],
            'tenant_subscription' => $this->buildTenantSubscriptionPayload((int) $cliente['id']),
        ];

        $jobModel->update((int) $job['id'], [
            'status' => 'processing',
            'attempt_count' => (int) $job['attempt_count'] + 1,
            'dispatched_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);

        $code = $this->postProvisionPayload($body, 10);
        if ($code < 200 || $code >= 300) {
            $msg = $code === 0 ? 'Dispatch falhou (rede ou timeout)' : 'Dispatch HTTP ' . $code;
            $jobModel->update((int) $job['id'], [
                'status' => 'failed',
                'last_error' => substr($msg, 0, 255),
            ]);
        }
    }

    /**
     * POST JSON para provisioning.dispatchUrl.
     */
    private function postProvisionPayload(array $body, int $timeoutSeconds = 10): int
    {
        $cfg = config('Provisioning');
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($cfg->dispatchToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $cfg->dispatchToken;
        }

        try {
            $response = service('curlrequest')->request('POST', $cfg->dispatchUrl, [
                'headers' => $headers,
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'http_errors' => false,
                'timeout' => $timeoutSeconds,
            ]);

            return $response->getStatusCode();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Dados da assinatura no painel para espelhar na tabela `subscriptions` do portal do tenant.
     *
     * @return array<string, mixed>
     */
    private function buildTenantSubscriptionPayload(int $clienteId): array
    {
        $subscriptionModel = new SubscriptionModel();
        $sub = $subscriptionModel->where('cliente_id', $clienteId)->orderBy('id', 'DESC')->first();

        if (! $sub) {
            return [
                'plan_slug' => 'free',
                'plan_name' => 'Free',
                'status' => 'trial',
                'gateway' => 'none',
                'gateway_subscription_id' => null,
                'started_at' => null,
                'next_billing_at' => null,
                'ends_at' => null,
            ];
        }

        $planModel = new PlanModel();
        $plan = $planModel->find((int) $sub['plan_id']);
        $slug = $plan ? trim((string) ($plan['slug'] ?? '')) : '';
        $name = $plan ? trim((string) ($plan['nome'] ?? '')) : '';
        if ($slug === '') {
            $slug = 'free';
        }
        if ($name === '') {
            $name = $slug;
        }

        $gatewaySubId = $sub['gateway_subscription_id'] ?? null;
        $gatewaySubId = ($gatewaySubId !== null && $gatewaySubId !== '')
            ? (string) $gatewaySubId
            : null;

        return [
            'plan_slug' => $slug,
            'plan_name' => $name,
            'status' => (string) ($sub['status'] ?? 'trial'),
            'gateway' => (string) ($sub['gateway'] ?? 'none'),
            'gateway_subscription_id' => $gatewaySubId,
            'started_at' => $this->nullableProvisioningDateTime($sub['started_at'] ?? null),
            'next_billing_at' => $this->nullableProvisioningDateTime($sub['next_billing_at'] ?? null),
            'ends_at' => $this->nullableProvisioningDateTime($sub['ends_at'] ?? null),
        ];
    }

    private function nullableProvisioningDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function buildDbName(string $subdomain): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9_]+/', '_', $subdomain) ?? 'tenant');
        $slug = trim($slug, '_');
        if ($slug === '') {
            $slug = 'tenant';
        }

        return substr($slug, 0, 64);
    }

    private function extractSubdomain(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $parts = explode('.', $host);
        $candidate = $parts[0] ?? '';
        $candidate = strtolower(trim($candidate));
        if (! preg_match('/^[a-z0-9][a-z0-9\-]{0,62}$/', $candidate)) {
            throw new \InvalidArgumentException('Subdomínio inválido para provisionamento.');
        }

        return $candidate;
    }
}
