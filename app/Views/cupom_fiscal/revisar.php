<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $importId = (int) ($import['id'] ?? 0); ?>
<div class="mb-3">
    <p class="text-secondary mb-0">Revise cada linha, associe a um produto já cadastrado para não duplicar, ou marque para criar novo produto.</p>
    <?php if (! empty($linhas_sem_dados_salvos)): ?>
        <div class="alert alert-warning mt-2 mb-0">
            Não foi possível carregar as linhas salvas desta importação (JSON vazio ou inválido). Foi aberta uma linha em branco.
            Se você acabou de enviar o cupom, confira o log no modal de processamento ou o texto extraído abaixo.
        </div>
    <?php endif; ?>
</div>

<?php if (! empty($import['texto_auxiliar'])): ?>
    <details class="card mb-3">
        <summary class="card-header cursor-pointer user-select-none">Texto auxiliar / extraído (depuração)</summary>
        <div class="card-body">
            <pre class="small mb-0 text-break" style="white-space: pre-wrap; max-height: 320px; overflow: auto;"><?= esc((string) $import['texto_auxiliar']) ?></pre>
        </div>
    </details>
<?php endif; ?>

<form method="post" action="<?= base_url('cupom-fiscal/salvar-massa/' . $importId) ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle card-table">
                <colgroup>
                    <col style="width: 70px;">
                    <col style="min-width: 220px;">
                    <col style="min-width: 360px;">
                    <col style="min-width: 160px;">
                    <col style="min-width: 120px;">
                    <col style="width: 110px;">
                    <col style="width: 110px;">
                    <col style="width: 80px;">
                    <col style="min-width: 180px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Incluir</th>
                        <th>Associar a</th>
                        <th>Nome</th>
                        <th>Categoria (novo)</th>
                        <th>Embalagem</th>
                        <th>Preco (R$)</th>
                        <th>Qtd. pacote</th>
                        <th>Un.</th>
                        <th>Obs.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($linhas ?? []) as $i => $linha): ?>
                        <tr>
                            <td class="text-center">
                                <input type="hidden" name="linhas[<?= $i ?>][incluir]" value="0">
                                <input type="checkbox" name="linhas[<?= $i ?>][incluir]" value="1" <?= ! empty($linha['incluir']) ? 'checked' : '' ?>>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" name="linhas[<?= $i ?>][produto_associado_id]">
                                    <option value="">— Novo produto —</option>
                                    <?php foreach (($produtos ?? []) as $pr): ?>
                                        <option value="<?= (int) $pr['id'] ?>" <?= (int) ($linha['produto_associado_id'] ?? 0) === (int) $pr['id'] ? 'selected' : '' ?>>
                                            <?= esc($pr['nome']) ?><?= ! empty($pr['categoria_nome']) ? ' (' . esc($pr['categoria_nome']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <textarea
                                    class="form-control form-control-sm"
                                    rows="2"
                                    name="linhas[<?= $i ?>][nome]"
                                    placeholder="Descricao do produto"
                                    style="min-width: 340px; resize: vertical;"
                                ><?= esc($linha['nome'] ?? '') ?></textarea>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" name="linhas[<?= $i ?>][categoria_id]">
                                    <option value="">—</option>
                                    <?php foreach (($categorias ?? []) as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>" <?= (string) ($linha['categoria_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
                                            <?= esc($c['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" name="linhas[<?= $i ?>][embalagem]" value="<?= esc($linha['embalagem'] ?? '') ?>"></td>
                            <td><input type="text" class="form-control form-control-sm" name="linhas[<?= $i ?>][preco]" value="<?= esc(rtrim(rtrim(number_format((float) ($linha['preco'] ?? 0), 2, ',', '.'), '0'), ',')) ?>"></td>
                            <td><input type="text" class="form-control form-control-sm" name="linhas[<?= $i ?>][qtd_embalagem]" value="<?= esc(rtrim(rtrim(number_format((float) ($linha['qtd_embalagem'] ?? 0), 4, ',', '.'), '0'), ',')) ?>"></td>
                            <td>
                                <?php $ue = $linha['un_embalagem'] ?? 'g'; ?>
                                <select class="form-select form-select-sm" name="linhas[<?= $i ?>][un_embalagem]">
                                    <?php foreach (['g', 'kg', 'ml', 'l', 'un'] as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $ue === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" name="linhas[<?= $i ?>][observacoes]" value="<?= esc($linha['observacoes'] ?? '') ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body">
            <button type="submit" class="btn btn-primary">Salvar produtos em massa</button>
            <a href="<?= base_url('cupom-fiscal') ?>" class="btn btn-link">Novo upload</a>
        </div>
    </div>
</form>
<script>
(() => {
    const w = sessionStorage.getItem('cupom_fiscal_warning');
    if (!w || !window.bootstrap?.Toast) return;
    sessionStorage.removeItem('cupom_fiscal_warning');
    const wrap = document.createElement('div');
    wrap.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    wrap.innerHTML = '<div class="toast border-warning show" role="alert"><div class="toast-header"><strong class="me-auto">Aviso</strong><button type="button" class="btn-close" data-bs-dismiss="toast"></button></div><div class="toast-body"></div></div>';
    wrap.querySelector('.toast-body').textContent = w;
    document.body.appendChild(wrap);
    new bootstrap.Toast(wrap.querySelector('.toast'), { delay: 12000 }).show();
})();
</script>
<?= $this->endSection() ?>
