<?php

namespace App\Services;

use App\Models\ClienteModel;
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
            throw new \InvalidArgumentException('Payload de callback invalido.');
        }

        $jobModel = new TenantProvisionJobModel();
        $clienteModel = new ClienteModel();

        $job = $jobModel->where('cliente_id', $clienteId)->first();
        if (! $job) {
            throw new \RuntimeException('Job de provisionamento nao encontrado.');
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
        ];

        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($cfg->dispatchToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $cfg->dispatchToken;
        }

        $jobModel->update((int) $job['id'], [
            'status' => 'processing',
            'attempt_count' => (int) $job['attempt_count'] + 1,
            'dispatched_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);

        try {
            $response = service('curlrequest')->request('POST', $cfg->dispatchUrl, [
                'headers' => $headers,
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'http_errors' => false,
                'timeout' => 10,
            ]);

            $code = $response->getStatusCode();
            if ($code < 200 || $code >= 300) {
                $jobModel->update((int) $job['id'], [
                    'status' => 'failed',
                    'last_error' => 'Dispatch HTTP ' . $code,
                ]);
            }
        } catch (\Throwable $e) {
            $jobModel->update((int) $job['id'], [
                'status' => 'failed',
                'last_error' => substr('Dispatch falhou: ' . $e->getMessage(), 0, 255),
            ]);
        }
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
            throw new \InvalidArgumentException('Subdominio invalido para provisionamento.');
        }

        return $candidate;
    }
}
