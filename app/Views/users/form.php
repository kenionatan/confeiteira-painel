<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $isEdit = isset($user['id']); ?>
<div class="card">
    <div class="card-body">
        <form method="post" action="<?= $isEdit ? '/usuarios/atualizar/' . $user['id'] : '/usuarios/salvar' ?>">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" required value="<?= esc(old('name', $user['name'] ?? '')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= esc(old('email', $user['email'] ?? '')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Senha <?= $isEdit ? '(deixe vazio para manter)' : '' ?></label>
                    <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?>>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirmar senha</label>
                    <input type="password" name="password_confirm" class="form-control" <?= $isEdit ? '' : 'required' ?>>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tema</label>
                    <?php $theme = old('preferred_theme', $user['preferred_theme'] ?? 'light'); ?>
                    <select name="preferred_theme" class="form-select" required>
                        <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>light</option>
                        <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>dark</option>
                    </select>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label d-block">Grupos</label>
                    <?php foreach ($groups as $group): ?>
                        <?php $checked = in_array((int) $group['id'], $selectedGroupIds, true) ? 'checked' : ''; ?>
                        <label class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?= esc($group['id']) ?>" <?= $checked ?>>
                            <span class="form-check-label"><?= esc($group['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-12 mb-3">
                    <?php $active = (int) old('is_active', $user['is_active'] ?? 1); ?>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>
                        <span class="form-check-label">Usuario ativo</span>
                    </label>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="/usuarios" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
