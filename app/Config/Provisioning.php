<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Provisioning extends BaseConfig
{
    /** Host central do painel/admin (nao tenant). */
    public string $centralHost = 'portal.appdoce.top';

    /** Dominio raiz para tenants no wildcard, ex.: cliente.appdoce.top. */
    public string $tenantRootDomain = 'appdoce.top';

    /** URL da automacao de provisionamento (AWS ou endpoint interno). */
    public string $dispatchUrl = '';

    /** Token enviado ao provisionador no header Authorization: Bearer <token>. */
    public string $dispatchToken = '';

    /** Token esperado no callback /provisioning/callback. */
    public string $callbackToken = '';

    /** Repo Git do portal do cliente (clonado para /var/www/html/{subdominio}). */
    public string $portalGitRepo = '';

    /** Branch/tag do portal. */
    public string $portalGitRef = 'main';

    /** Base path para provisionar pastas do portal no servidor. */
    public string $portalBasePath = '/var/www/html';

    public function __construct()
    {
        parent::__construct();

        $this->centralHost = (string) env('provisioning.centralHost', $this->centralHost);
        $this->tenantRootDomain = (string) env('provisioning.tenantRootDomain', $this->tenantRootDomain);
        $this->dispatchUrl = (string) env('provisioning.dispatchUrl', $this->dispatchUrl);
        $this->dispatchToken = (string) env('provisioning.dispatchToken', $this->dispatchToken);
        $this->callbackToken = (string) env('provisioning.callbackToken', $this->callbackToken);
        $this->portalGitRepo = (string) env('provisioning.portalGitRepo', $this->portalGitRepo);
        $this->portalGitRef = (string) env('provisioning.portalGitRef', $this->portalGitRef);
        $this->portalBasePath = (string) env('provisioning.portalBasePath', $this->portalBasePath);
    }
}
