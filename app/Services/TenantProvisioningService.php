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

        $plannedDbName = (string) $data['requested_db_name'];
        $clienteModel->update((int) $cliente['id'], [
            'tenant_status' => 'pending',
            'tenant_error_message' => null,
            'tenant_ready_at' => null,
            // Nomes planejados no nosso lado (não exigem o servidor remoto); espelham o job.
            'tenant_db_name' => substr($plannedDbName, 0, 80),
            'tenant_db_user' => substr($subdomain, 0, 80),
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

            $dbName = trim((string) ($payload['db_name'] ?? ''));
            if ($dbName === '') {
                $dbName = trim((string) ($job['requested_db_name'] ?? ''));
            }
            if ($dbName !== '') {
                $clienteUpdate['tenant_db_name'] = substr($dbName, 0, 80);
            }

            $dbUser = trim((string) ($payload['db_user'] ?? ''));
            if ($dbUser === '') {
                try {
                    $dbUser = $this->extractSubdomain((string) ($job['requested_host'] ?? ''));
                } catch (\Throwable) {
                    $dbUser = '';
                }
            }
            if ($dbUser !== '') {
                $clienteUpdate['tenant_db_user'] = substr($dbUser, 0, 80);
            }
        }

        $clienteModel->update($clienteId, $clienteUpdate);
    }

    /**
     * Reenvia só os dados de assinatura para o provisionador (action sync_subscription_only).
     *
     * @return array{success: bool, http_code: int, response_body: string, message: string}
     */
    public function dispatchSubscriptionSync(int $clienteId): array
    {
        $fail = static fn (string $message): array => [
            'success' => false,
            'http_code' => 0,
            'response_body' => '',
            'message' => $message,
        ];

        $cfg = config('Provisioning');
        if ($cfg->dispatchUrl === '') {
            return $fail('provisioning.dispatchUrl está vazio.');
        }

        $clienteModel = new ClienteModel();
        $cliente = $clienteModel->find($clienteId);
        if (! $cliente) {
            return $fail("Cliente {$clienteId} não encontrado.");
        }

        $dbName = trim((string) ($cliente['tenant_db_name'] ?? ''));
        $backfilled = false;
        if ($dbName === '') {
            $jobModel = new TenantProvisionJobModel();
            $job = $jobModel->where('cliente_id', $clienteId)->orderBy('id', 'DESC')->first();
            if ($job) {
                $dbName = trim((string) ($job['requested_db_name'] ?? ''));
            }
            if ($dbName === '') {
                return $fail('tenant_db_name vazio no cliente e requested_db_name ausente no job de provisionamento.');
            }
            $dbUserFallback = '';
            $hostForUser = $job !== null ? (string) ($job['requested_host'] ?? '') : '';
            if ($hostForUser === '') {
                $hostForUser = (string) ($cliente['dominio'] ?? '');
            }
            try {
                $dbUserFallback = $this->extractSubdomain($hostForUser);
            } catch (\Throwable) {
                $dbUserFallback = '';
            }
            $fill = ['tenant_db_name' => substr($dbName, 0, 80)];
            if ($dbUserFallback !== '') {
                $fill['tenant_db_user'] = substr($dbUserFallback, 0, 80);
            }
            $clienteModel->update($clienteId, $fill);
            $backfilled = true;
        }

        $dominio = strtolower(trim((string) ($cliente['dominio'] ?? '')));
        try {
            $subdomain = $this->extractSubdomain($dominio);
        } catch (\Throwable) {
            return $fail('dominio do cliente inválido para extrair subdomínio.');
        }

        $body = [
            'action' => 'sync_subscription_only',
            'cliente_id' => $clienteId,
            'requested_subdomain' => $subdomain,
            'requested_db_name' => $dbName,
            'tenant_subscription' => $this->buildTenantSubscriptionPayload($clienteId),
        ];

        $result = $this->postProvisionPayloadResult($body, 30);
        $code = $result['code'];
        $responseBody = $result['body'];
        if ($code === 0) {
            $hint = $result['curl_error'] !== '' ? ' (' . $result['curl_error'] . ')' : '';

            return [
                'success' => false,
                'http_code' => 0,
                'response_body' => $responseBody,
                'message' => 'Falha de rede ou timeout ao chamar dispatchUrl.' . $hint,
            ];
        }

        $success = $code >= 200 && $code < 300;
        $msg = $success
            ? ($backfilled ? 'OK (tenant_db_name preenchido a partir do job.)' : 'OK.')
            : 'HTTP ' . $code;

        return [
            'success' => $success,
            'http_code' => $code,
            'response_body' => $responseBody,
            'message' => $msg,
        ];
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

        $timeout = max(30, $cfg->dispatchTimeout);
        $result = $this->postProvisionPayloadResult($body, $timeout);
        $code = $result['code'];
        if ($code < 200 || $code >= 300) {
            $bodySnippet = trim((string) preg_replace('/\s+/', ' ', $result['body']));
            $detail = $code === 0
                ? ('Dispatch falhou (rede ou timeout)' . ($result['curl_error'] !== '' ? ': ' . $result['curl_error'] : ''))
                : ('Dispatch HTTP ' . $code . ' — ' . substr($bodySnippet, 0, 200));
            $err = substr($detail, 0, 255);
            $jobModel->update((int) $job['id'], [
                'status' => 'failed',
                'last_error' => $err,
            ]);
            (new ClienteModel())->update((int) $cliente['id'], [
                'tenant_status' => 'failed',
                'tenant_error_message' => $err,
            ]);

            return;
        }

        // Provisionamento síncrono concluiu com HTTP 2xx: grava metadados mesmo se o callback HTTP falhou
        // (ex.: curl no script sem --fail retornava 0 com 401).
        $this->markTenantReadyFromProvisionJob((int) $job['id'], (int) $cliente['id']);
    }

    /**
     * Marca job como concluído e preenche tenant_db_* no cliente a partir do job (espelho do callback ready).
     */
    private function markTenantReadyFromProvisionJob(int $jobId, int $clienteId): void
    {
        $jobModel = new TenantProvisionJobModel();
        $job = $jobModel->find($jobId);
        if (! $job) {
            return;
        }

        if (($job['status'] ?? '') === 'completed') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $dbName = trim((string) ($job['requested_db_name'] ?? ''));
        $host = (string) ($job['requested_host'] ?? '');
        $dbUser = '';
        try {
            $dbUser = $this->extractSubdomain($host);
        } catch (\Throwable) {
            $dbUser = '';
        }

        $jobModel->update($jobId, [
            'status' => 'completed',
            'last_error' => null,
            'completed_at' => $now,
            'payload_json' => json_encode([
                'source' => 'dispatch_http_success',
                'cliente_id' => $clienteId,
                'db_name' => $dbName,
                'db_user' => $dbUser,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $clienteUpdate = [
            'tenant_status' => 'ready',
            'tenant_ready_at' => $now,
            'tenant_error_message' => null,
        ];
        if ($dbName !== '') {
            $clienteUpdate['tenant_db_name'] = substr($dbName, 0, 80);
        }
        if ($dbUser !== '') {
            $clienteUpdate['tenant_db_user'] = substr($dbUser, 0, 80);
        }

        (new ClienteModel())->update($clienteId, $clienteUpdate);
    }

    /**
     * POST JSON para provisioning.dispatchUrl (apenas código HTTP).
     */
    private function postProvisionPayload(array $body, int $timeoutSeconds = 10): int
    {
        return $this->postProvisionPayloadResult($body, $timeoutSeconds)['code'];
    }

    /**
     * POST JSON para provisioning.dispatchUrl.
     *
     * @return array{code: int, body: string, curl_error: string}
     */
    private function postProvisionPayloadResult(array $body, int $timeoutSeconds = 10): array
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

            return [
                'code' => $response->getStatusCode(),
                'body' => (string) $response->getBody(),
                'curl_error' => '',
            ];
        } catch (\Throwable $e) {
            return [
                'code' => 0,
                'body' => '',
                'curl_error' => $e->getMessage(),
            ];
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
