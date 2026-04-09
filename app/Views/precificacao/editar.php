<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <form method="post" action="/precificacao/atualizar/<?= esc($item['id']) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome do produto</label>
                    <input type="text" name="nome_produto" class="form-control" required value="<?= esc(old('nome_produto', $item['nome_produto'])) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Categoria</label>
                    <input type="text" name="categoria" class="form-control" value="<?= esc(old('categoria', $item['categoria'])) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Custo (R$)</label>
                    <input type="text" name="custo" class="form-control" value="<?= esc(old('custo', number_format((float) $item['custo'], 2, ',', '.'))) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Margem (%)</label>
                    <input type="text" name="margem_percentual" class="form-control" value="<?= esc(old('margem_percentual', number_format((float) $item['margem_percentual'], 2, ',', '.'))) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Preco sugerido (atual)</label>
                    <input type="text" class="form-control" readonly value="R$ <?= esc(number_format((float) $item['preco_sugerido'], 2, ',', '.')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Observacoes</label>
                    <textarea name="observacoes" class="form-control" rows="5"><?= esc(old('observacoes', $item['observacoes'])) ?></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Salvar alteracoes</button>
                <a href="/precificacao" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
