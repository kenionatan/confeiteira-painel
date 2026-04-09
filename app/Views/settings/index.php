<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <form method="post" action="/configuracoes/salvar">
            <?= csrf_field() ?>
            <h3 class="card-title">Nome e identidade visual</h3>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nome do app (titulo)</label>
                    <input type="text" name="app_name" class="form-control" required value="<?= esc(old('app_name', $settings['app_name'] ?? 'Confeiteira App')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cor predominante dos botoes</label>
                    <input type="color" name="title_color" class="form-control form-control-color" value="<?= esc(old('title_color', $settings['title_color'] ?? '#8b5cf6')) ?>">
                    <small class="text-secondary">Essa cor define o degradê principal dos botoes e elementos de destaque.</small>
                </div>
            </div>

            <h3 class="card-title mt-4">Tema</h3>
            <?php $theme = old('preferred_theme', $currentUser['preferred_theme'] ?? 'light'); ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tema da interface</label>
                    <select name="preferred_theme" class="form-select" required>
                        <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
            </div>

            <button class="btn btn-primary" type="submit">Salvar configuracoes</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
