<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<div class="card">
    <div class="card-body">
        <form action="/pedidos/atualizar/<?= esc($pedido['id']) ?>" method="post">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select" id="cliente-select">
                        <option value="">Selecione um cliente</option>
                        <?php foreach (($clientes ?? []) as $cliente): ?>
                            <option value="<?= esc($cliente['id']) ?>"><?= esc($cliente['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-secondary">Ou informe manualmente abaixo.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cliente (manual)</label>
                    <input type="text" name="cliente_nome" class="form-control" value="<?= esc(old('cliente_nome', $pedido['cliente_nome'])) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Itens do pedido</label>
                    <div class="row g-2 mb-2">
                        <div class="col-md-7">
                            <select class="form-select" id="produto-select">
                                <option value="">Selecione um produto cadastrado</option>
                                <?php foreach (($produtos ?? []) as $produto): ?>
                                    <option value="<?= esc($produto['id']) ?>" data-nome="<?= esc($produto['nome_produto']) ?>" data-preco="<?= esc(number_format((float) $produto['preco_sugerido'], 2, '.', '')) ?>">
                                        <?= esc($produto['nome_produto']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" min="1" step="1" class="form-control" id="item-quantidade" value="1">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" type="button" id="btn-add-item">Adicionar item</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Produto</th><th>Qtd</th><th>Valor unitario</th><th>Subtotal</th><th></th></tr>
                            </thead>
                            <tbody id="pedido-itens-body"></tbody>
                            <tfoot>
                                <tr><th colspan="3" class="text-end">Total</th><th id="pedido-total">R$ 0,00</th><th></th></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach (['novo','em_producao','finalizado','entregue','cancelado'] as $st): ?>
                            <option value="<?= esc($st) ?>" <?= ($pedido['status'] ?? '') === $st ? 'selected' : '' ?>><?= esc($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data de entrega</label>
                    <input type="date" name="data_entrega" class="form-control" value="<?= esc($pedido['data_entrega'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Observacoes</label>
                    <textarea name="observacoes" class="form-control" rows="4"></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
                <a href="/pedidos/ver/<?= esc($pedido['id']) ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(() => {
    const existing = <?= json_encode($itens ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const produtoSelect = document.getElementById('produto-select');
    const qtdInput = document.getElementById('item-quantidade');
    const addBtn = document.getElementById('btn-add-item');
    const itensBody = document.getElementById('pedido-itens-body');
    const totalEl = document.getElementById('pedido-total');
    const form = document.querySelector('form');
    let idx = 0;
    const formatBRL = (n) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(n || 0));
    const recalc = () => {
        let t = 0;
        itensBody.querySelectorAll('tr[data-item]').forEach((tr) => t += Number(tr.getAttribute('data-subtotal') || '0'));
        totalEl.textContent = formatBRL(t);
    };
    const addRow = (produtoId, nome, preco, qtd) => {
        const subtotal = Number(preco) * Number(qtd);
        const tr = document.createElement('tr');
        tr.setAttribute('data-item', '1');
        tr.setAttribute('data-subtotal', String(subtotal));
        tr.innerHTML = `<td>${nome}<input type="hidden" name="linhas[${idx}][produto_id]" value="${produtoId}"></td>
                        <td>${qtd}<input type="hidden" name="linhas[${idx}][quantidade]" value="${qtd}"></td>
                        <td>${formatBRL(preco)}</td><td>${formatBRL(subtotal)}</td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger js-del">x</button></td>`;
        itensBody.appendChild(tr);
        idx++;
        recalc();
    };
    existing.forEach((i) => addRow(i.produto_id || '', i.produto_nome || '-', i.valor_unitario || 0, i.quantidade || 1));
    addBtn?.addEventListener('click', () => {
        const opt = produtoSelect.options[produtoSelect.selectedIndex];
        if (!opt || !opt.value) return;
        addRow(opt.value, opt.getAttribute('data-nome') || opt.textContent || '', parseFloat(opt.getAttribute('data-preco') || '0'), parseInt(qtdInput.value || '1', 10));
    });
    itensBody.addEventListener('click', (e) => {
        const d = e.target.closest('.js-del');
        if (!d) return;
        d.closest('tr')?.remove();
        recalc();
    });
    form?.addEventListener('submit', (e) => {
        if (!itensBody.querySelector('tr[data-item]')) {
            e.preventDefault();
            alert('Adicione pelo menos um item.');
        }
    });
    if (window.jQuery && window.jQuery.fn.select2) {
        window.jQuery('#cliente-select').select2({ placeholder: 'Selecione um cliente', allowClear: true, width: '100%' });
        window.jQuery('#produto-select').select2({ placeholder: 'Selecione um produto', allowClear: true, width: '100%' });
    }
})();
</script>
<?= $this->endSection() ?>
