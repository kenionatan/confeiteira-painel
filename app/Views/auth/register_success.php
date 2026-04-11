<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Cadastro recebido') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.3.2/dist/css/tabler.min.css">
</head>
<body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="card card-md">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <span class="avatar avatar-lg bg-success-lt text-success">✓</span>
                    </div>
                    <h1 class="h2 mb-3">Cadastro recebido</h1>
                    <?php
                    $slug = strtolower((string) ($planSlug ?? 'free'));
                    $isPaid = in_array($slug, ['basico', 'pro'], true);
                    $tenantDomain = trim((string) ($tenantDomain ?? ''));
                    $tenantUrl = $tenantDomain !== '' ? 'https://' . preg_replace('#^https?://#i', '', $tenantDomain) : '';
                    $supportEmail = 'bitdrop.store@gmail.com';
                    ?>
                    <?php if ($isPaid): ?>
                        <p class="text-secondary mb-0">
                            Sua conta no plano <strong><?= esc($planNome ?? '') ?></strong> foi criada e a primeira mensalidade foi cobrada com sucesso.
                            <?php if (! empty($email)): ?>
                                As próximas orientações serão enviadas para <strong><?= esc($email) ?></strong>.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="text-secondary mb-0">
                            Obrigado por se cadastrar no plano <?= esc($planNome ?? 'Free') ?>.
                            <?php if (! empty($email)): ?>
                                Enviamos as próximas orientações para <strong><?= esc($email) ?></strong> quando o ambiente estiver pronto.
                            <?php endif; ?>
                        </p>
                        <p class="text-secondary mt-3 mb-0">
                            Nossa equipe pode entrar em contato pelo WhatsApp informado para alinhar detalhes, se necessário.
                        </p>
                    <?php endif; ?>

                    <div class="alert alert-info mt-4 text-start d-flex flex-column gap-2" role="alert">
                        <div>
                            <strong>Provisionamento em andamento</strong>
                        </div>
                        <p class="mb-0">
                            Estamos preparando seu ambiente dedicado. Em alguns minutos o link da sua plataforma estará totalmente disponível.
                        </p>
                    </div>

                    <p class="text-secondary small text-start mb-0 mt-3">
                        Problemas para acessar ou dúvidas?
                        Escreva para
                        <a href="mailto:<?= esc($supportEmail, 'attr') ?>"><?= esc($supportEmail) ?></a>
                        e ajudamos você.
                    </p>

                    <?php if ($tenantUrl !== ''): ?>
                        <div class="mt-4 pt-3 border-top text-center">
                            <p class="text-secondary small mb-2">Seu portal</p>
                            <p class="mb-2">
                                <a href="<?= esc($tenantUrl, 'attr') ?>" class="h4 link-primary text-break d-inline-block" rel="noopener"><?= esc($tenantDomain) ?></a>
                            </p>
                            <p class="text-secondary small mb-0">
                                Use o <strong>e-mail</strong> e a <strong>senha</strong> deste cadastro para entrar.
                            </p>
                        </div>
                    <?php else: ?>
                        <p class="text-secondary small text-center mt-4 mb-0">
                            Quando o ambiente estiver pronto, você receberá o endereço do seu portal por e-mail.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
