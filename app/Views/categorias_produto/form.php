<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="col-lg-6 mx-auto">
    <div class="card">
        <div class="card-body">
            <h3 class="card-title"><?= esc($title ?? 'Categoria') ?></h3>
            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger"><?= esc(implode(' ', (array) session()->getFlashdata('errors'))) ?></div>
            <?php endif; ?>
            <form method="post" action="/categorias-produto/atualizar/<?= (int) ($categoria['id'] ?? 0) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" name="nome" value="<?= esc(old('nome', $categoria['nome'] ?? '')) ?>" required maxlength="80">
                </div>
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a href="/categorias-produto" class="btn btn-link">Voltar</a>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
