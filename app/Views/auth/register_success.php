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
                    <p class="text-secondary mb-0">
                        Obrigado por se cadastrar no plano Free. Em breve voce recebera um e-mail
                        <?php if (! empty($email)): ?>
                            em <strong><?= esc($email) ?></strong>
                        <?php endif; ?>
                        com informacoes sobre o seu dominio e os proximos passos.
                    </p>
                    <p class="text-secondary mt-3 mb-0">
                        Nossa equipe pode entrar em contato pelo WhatsApp informado para alinhar detalhes, se necessario.
                    </p>
                    <div class="mt-4">
                        <a href="/" class="btn btn-primary w-100">Voltar ao inicio</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
