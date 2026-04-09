<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
    <div class="card">
        <div class="card-body">
            <form action="/precificacao/salvar" method="post">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome do produto</label>
                        <input type="text" name="nome_produto" class="form-control" value="<?= esc(old('nome_produto')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categoria</label>
                        <input type="text" name="categoria" class="form-control" value="<?= esc(old('categoria')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Custo (R$)</label>
                        <input type="number" name="custo" step="0.01" min="0" class="form-control" value="<?= esc(old('custo')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Margem (%)</label>
                        <input type="number" name="margem_percentual" step="0.01" min="0" class="form-control" value="<?= esc(old('margem_percentual')) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observacoes</label>
                        <textarea name="observacoes" class="form-control" rows="4"><?= esc(old('observacoes')) ?></textarea>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="/precificacao" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
<?= $this->endSection() ?>
