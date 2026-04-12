<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $settings = app_settings(); ?>
    <?php $theme = session()->get('theme') ?: 'light'; ?>
    <?php
    $appName = $settings['app_name'] ?? 'Confeiteira App';
    $buttonColor = $settings['title_color'] ?? '#8b5cf6';
    $request = service('request');
    $path = trim((string) $request->getUri()->getPath(), '/');
    $parts = $path === '' ? [] : explode('/', $path);
    $painelIdx = array_search('painel', $parts, true);
    $afterPainel = ($painelIdx !== false) ? strtolower((string) ($parts[$painelIdx + 1] ?? '')) : '';
    $isActive = static function (string $name) use ($afterPainel): string {
        if ($name === 'dashboard') {
            return $afterPainel === '' ? 'active' : '';
        }
        if ($name === 'clientes') {
            return str_starts_with($afterPainel, 'clientes') ? 'active' : '';
        }

        return '';
    };
    ?>
    <title><?= esc($title ?? 'Dashboard') ?> - <?= esc($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.3.2/dist/css/tabler.min.css">
    <link rel="stylesheet" href="/css/app.css">
    <style>:root{--accent-dynamic:<?= esc($buttonColor) ?>;}</style>
    
</head>
<body>
    <script>document.documentElement.setAttribute('data-theme', '<?= esc($theme) ?>');</script>
    <div class="page">
        <header class="navbar navbar-expand-md d-print-none">
            <div class="container-xl">
                <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-3 app-brand">
                    <?= esc($appName) ?>
                </h1>
                <div class="navbar-nav flex-row order-md-last">
                    <?php if (current_user()): ?>
                        <form action="/tema/ajax" method="post" class="me-2 d-none" id="theme-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="preferred_theme" id="theme-input" value="<?= esc($theme) ?>">
                            <button type="button" class="btn btn-icon theme-toggle-btn" id="theme-toggle-btn" title="Alternar tema">
                                <span class="theme-icon-sun" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M5 5l1.5 1.5M17.5 17.5L19 19M2 12h2M20 12h2M5 19l1.5-1.5M17.5 6.5L19 5"/></svg>
                                </span>
                                <span class="theme-icon-moon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a9 9 0 1 0 9 9 7 7 0 0 1-9-9z"/></svg>
                                </span>
                            </button>
                        </form>
                        <div class="dropdown" data-bs-display="static">
                            <a href="#" class="nav-link d-flex lh-1 text-reset p-0 user-menu-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu do usuário">
                                <span class="avatar avatar-sm"><?= esc(strtoupper(substr((string) current_user()['name'], 0, 1))) ?></span>
                                <div class="d-none d-xl-block ps-2">
                                    <div><?= esc(current_user()['name']) ?></div>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <a href="/painel/logout" class="dropdown-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 8l-4 4 4 4"/><path d="M5 12h14"/><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/></svg>
                                    Sair
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <header class="navbar-expand-md">
            <div class="collapse navbar-collapse" id="navbar-menu">
                <div class="navbar">
                    <div class="container-xl">
                        <ul class="navbar-nav">
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('dashboard') ?>" href="/painel">
                                    <span class="nav-link-title">Dashboard</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('clientes') ?>" href="/painel/clientes">
                                    <span class="nav-link-title">Clientes</span>
                                </a>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="/">Landing</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-wrapper">
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <h2 class="page-title"><?= esc($title ?? 'Confeiteira App') ?></h2>
                </div>
            </div>

            <div class="page-body">
                <div class="container-xl">
                    <?= $this->renderSection('content') ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    $successFlash = session()->getFlashdata('success');
    $warningFlash = session()->getFlashdata('warning');
    $errorsFlash = session()->getFlashdata('errors');
    $toastMessages = [];
    if (! empty($successFlash)) {
        $toastMessages[] = ['type' => 'success', 'title' => 'Sucesso', 'message' => (string) $successFlash];
    }
    if (! empty($warningFlash)) {
        $toastMessages[] = ['type' => 'warning', 'title' => 'Aviso', 'message' => (string) $warningFlash];
    }
    if (! empty($errorsFlash) && is_array($errorsFlash)) {
        foreach ($errorsFlash as $msg) {
            $toastMessages[] = ['type' => 'danger', 'title' => 'Atencao', 'message' => (string) $msg];
        }
    }
    ?>
    <?php if (! empty($toastMessages)): ?>
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <?php foreach ($toastMessages as $i => $toast): ?>
                <div
                    class="toast border-<?= esc($toast['type']) ?> show mb-2"
                    role="alert"
                    aria-live="assertive"
                    aria-atomic="true"
                    data-bs-delay="3500"
                    id="app-toast-<?= esc((string) $i) ?>"
                >
                    <div class="toast-header">
                        <strong class="me-auto"><?= esc($toast['title']) ?></strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body"><?= esc($toast['message']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <?= $this->renderSection('scripts') ?>
    <script>
        (() => {
            const form = document.getElementById('theme-form');
            const input = document.getElementById('theme-input');
            const btn = document.getElementById('theme-toggle-btn');
            if (!form || !input || !btn) return;

            btn.addEventListener('click', async () => {
                const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                const selected = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', selected);
                input.value = selected;

                const formData = new FormData(form);
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });
                    if (!response.ok) throw new Error('Tema não salvo');
                } catch (error) {
                    document.documentElement.setAttribute('data-theme', '<?= esc($theme) ?>');
                    input.value = '<?= esc($theme) ?>';
                }
            });

            document.querySelectorAll('.toast').forEach((el) => {
                const toast = new bootstrap.Toast(el);
                toast.show();
            });
        })();
    </script>
</body>
</html>

