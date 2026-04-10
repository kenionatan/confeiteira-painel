<?php

namespace App\Controllers;

use App\Services\TenantProvisioningService;

class ProvisioningController extends BaseController
{
    /**
     * Callback da automacao AWS para atualizar status de provisionamento.
     */
    public function callback()
    {
        $cfg = config('Provisioning');
        if ($cfg->callbackToken === '') {
            return $this->response->setStatusCode(503)->setJSON([
                'ok' => false,
                'error' => 'Callback token nao configurado.',
            ]);
        }

        $auth = trim((string) $this->request->getHeaderLine('Authorization'));
        $expected = 'Bearer ' . $cfg->callbackToken;
        if (! hash_equals($expected, $auth)) {
            return $this->response->setStatusCode(401)->setJSON([
                'ok' => false,
                'error' => 'Nao autorizado.',
            ]);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        try {
            (new TenantProvisioningService())->applyCallback($payload);

            return $this->response->setJSON(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok' => false,
                'error' => ENVIRONMENT !== 'production' ? $e->getMessage() : 'Payload invalido.',
            ]);
        }
    }
}
