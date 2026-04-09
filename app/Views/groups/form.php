<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $isEdit = isset($group['id']); ?>
<div class="card">
    <div class="card-body">
        <form method="post" action="<?= $isEdit ? '/grupos/atualizar/' . $group['id'] : '/grupos/salvar' ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Nome do grupo</label>
                <input type="text" name="name" class="form-control" required value="<?= esc(old('name', $group['name'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Descricao</label>
                <input type="text" name="description" class="form-control" value="<?= esc(old('description', $group['description'] ?? '')) ?>">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="/grupos" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
