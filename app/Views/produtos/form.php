<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$isEdit = ! empty($produto['id']);
$action = $isEdit ? '/produtos/atualizar/' . (int) $produto['id'] : '/produtos/salvar';
?>
<div class="col-lg-8 mx-auto">
    <div class="card">
        <div class="card-body">
            <h3 class="card-title"><?= esc($title ?? 'Produto') ?></h3>
            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger">
                    <?php foreach ((array) session()->getFlashdata('errors') as $err): ?>
                        <div><?= esc(is_array($err) ? implode(' ', $err) : $err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= esc($action) ?>" id="form-produto">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" name="nome" value="<?= esc(old('nome', $produto['nome'] ?? '')) ?>" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoria</label>
                    <select class="form-select" name="categoria_id">
                        <option value="">—</option>
                        <?php foreach (($categorias ?? []) as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (string) old('categoria_id', $produto['categoria_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
                                <?= esc($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo de embalagem (opcional)</label>
                    <input type="text" class="form-control" name="embalagem" value="<?= esc(old('embalagem', $produto['embalagem'] ?? '')) ?>" placeholder="Ex.: pacote, lata" maxlength="120">
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Preco do pacote (R$)</label>
                        <input type="text" class="form-control js-brl" name="preco" id="prod-preco" value="<?= esc(old('preco', isset($produto['preco']) ? number_format((float) $produto['preco'], 2, ',', '') : '0,00')) ?>" inputmode="decimal">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Quantidade no pacote</label>
                        <input type="text" class="form-control js-qty" name="qtd_embalagem" id="prod-qtd" value="<?= esc(old('qtd_embalagem', isset($produto['qtd_embalagem']) ? rtrim(rtrim(number_format((float) $produto['qtd_embalagem'], 4, ',', '.'), '0'), ',') : '0')) ?>" inputmode="decimal">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Unidade do pacote</label>
                        <select class="form-select" name="un_embalagem">
                            <?php $u = old('un_embalagem', $produto['un_embalagem'] ?? 'g'); ?>
                            <?php foreach (['g', 'kg', 'ml', 'l', 'un'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $u === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Observacoes</label>
                    <textarea class="form-control" name="observacoes" rows="2"><?= esc(old('observacoes', $produto['observacoes'] ?? '')) ?></textarea>
                </div>
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a href="/produtos" class="btn btn-link">Voltar</a>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
    const form = document.getElementById('form-produto');
    if (!form) return;
    function bindMoneyMask(el) {
        el.addEventListener('input', () => {
            const digits = String(el.value).replace(/\D/g, '');
            if (!digits) { el.value = ''; return; }
            el.value = (parseInt(digits, 10) / 100).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });
        el.addEventListener('blur', () => {
            if (!String(el.value).trim()) el.value = '0,00';
        });
    }
    function bindQtyMask(el) {
        el.addEventListener('input', () => {
            let s = String(el.value).replace(/[^\d.,]/g, '');
            const c = s.indexOf(',');
            if (c !== -1) s = s.slice(0, c + 1) + s.slice(c + 1).replace(/,/g, '');
            el.value = s;
        });
    }
    form.querySelectorAll('.js-brl').forEach(bindMoneyMask);
    form.querySelectorAll('.js-qty').forEach(bindQtyMask);
})();
</script>
<?= $this->endSection() ?>
