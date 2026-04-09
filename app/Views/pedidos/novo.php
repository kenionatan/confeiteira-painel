<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <form action="/pedidos/salvar" method="post" id="form-novo-pedido">
        <?= csrf_field() ?>
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="card-title">Cabecalho do pedido</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <select name="cliente_id" class="form-select" id="cliente-select">
                            <option value="">Selecione um cliente</option>
                            <?php foreach (($clientes ?? []) as $cliente): ?>
                                <option value="<?= esc($cliente['id']) ?>" <?= (string) old('cliente_id') === (string) $cliente['id'] ? 'selected' : '' ?>>
                                    <?= esc($cliente['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-secondary">Nao encontrou? <a href="/clientes">Cadastre cliente</a> ou preencha manualmente abaixo.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cliente (manual)</label>
                        <input type="text" name="cliente_nome" class="form-control" value="<?= esc(old('cliente_nome')) ?>" placeholder="Opcional se selecionar cliente">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="novo" <?= old('status') === 'novo' ? 'selected' : '' ?>>Novo</option>
                            <option value="em_producao" <?= old('status') === 'em_producao' ? 'selected' : '' ?>>Em producao</option>
                            <option value="finalizado" <?= old('status') === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                            <option value="entregue" <?= old('status') === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                            <option value="cancelado" <?= old('status') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data de entrega</label>
                        <input type="date" name="data_entrega" class="form-control" value="<?= esc(old('data_entrega')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Observacoes</label>
                        <textarea name="observacoes" class="form-control" rows="1"><?= esc(old('observacoes')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Itens do pedido</h3>
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <select class="form-select" id="produto-select">
                            <option value="">Selecione um produto cadastrado</option>
                            <?php foreach (($produtos ?? []) as $produto): ?>
                                <option
                                    value="<?= esc($produto['id']) ?>"
                                    data-nome="<?= esc($produto['nome_produto']) ?>"
                                    data-preco="<?= esc(number_format((float) $produto['preco_sugerido'], 2, '.', '')) ?>"
                                >
                                    <?= esc($produto['nome_produto']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" min="1" step="1" class="form-control" id="item-quantidade" value="1">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-soft-action w-100" type="button" id="btn-add-item">Adicionar item</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Qtd</th>
                                <th>Valor unitario</th>
                                <th>Subtotal</th>
                                <th class="text-end">Acoes</th>
                            </tr>
                        </thead>
                        <tbody id="pedido-itens-body"></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total</th>
                                <th id="pedido-total">R$ 0,00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="/pedidos" class="btn btn-icon-soft-text">Cancelar</a>
                </div>
            </div>
        </div>
    </form>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (() => {
            const clienteSelect = document.getElementById('cliente-select');
            const produtoSelect = document.getElementById('produto-select');
            const qtdInput = document.getElementById('item-quantidade');
            const addBtn = document.getElementById('btn-add-item');
            const itensBody = document.getElementById('pedido-itens-body');
            const totalEl = document.getElementById('pedido-total');
            const form = document.getElementById('form-novo-pedido');
            let itemIndex = 0;

            const formatBRL = (n) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(n || 0));

            const recalcTotal = () => {
                let total = 0;
                itensBody.querySelectorAll('tr[data-item]').forEach((tr) => {
                    total += Number(tr.getAttribute('data-subtotal') || '0');
                });
                totalEl.textContent = formatBRL(total);
            };

            const addRow = () => {
                if (!produtoSelect) return;
                const opt = produtoSelect.options[produtoSelect.selectedIndex];
                const produtoId = opt ? opt.value : '';
                if (!produtoId) return;
                const nome = opt.getAttribute('data-nome') || opt.textContent || '';
                const preco = parseFloat(opt.getAttribute('data-preco') || '0');
                const quantidade = parseInt(qtdInput?.value || '1', 10);
                if (!Number.isFinite(quantidade) || quantidade <= 0) return;
                const subtotal = preco * quantidade;

                const tr = document.createElement('tr');
                tr.setAttribute('data-item', '1');
                tr.setAttribute('data-subtotal', String(subtotal));
                tr.innerHTML = `
                    <td>
                        ${nome}
                        <input type="hidden" name="linhas[${itemIndex}][produto_id]" value="${produtoId}">
                    </td>
                    <td>
                        ${quantidade}
                        <input type="hidden" name="linhas[${itemIndex}][quantidade]" value="${quantidade}">
                    </td>
                    <td>${formatBRL(preco)}</td>
                    <td>${formatBRL(subtotal)}</td>
                    <td class="text-end"><button type="button" class="btn btn-sm btn-icon-soft-danger js-del-item" title="Remover"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg></button></td>
                `;
                itensBody.appendChild(tr);
                itemIndex += 1;
                recalcTotal();
            };

            addBtn?.addEventListener('click', addRow);
            itensBody?.addEventListener('click', (e) => {
                const del = e.target.closest('.js-del-item');
                if (!del) return;
                del.closest('tr')?.remove();
                recalcTotal();
            });
            form?.addEventListener('submit', (e) => {
                if (!itensBody.querySelector('tr[data-item]')) {
                    e.preventDefault();
                    alert('Adicione pelo menos um item ao pedido.');
                }
            });

            if (!window.jQuery || !window.jQuery.fn.select2) return;
            if (clienteSelect) {
                window.jQuery(clienteSelect).select2({
                    placeholder: 'Selecione um cliente',
                    allowClear: true,
                    width: '100%'
                });
            }
            if (produtoSelect) {
                window.jQuery(produtoSelect).select2({
                    placeholder: 'Selecione um produto cadastrado',
                    allowClear: true,
                    width: '100%'
                });
            }
        })();
    </script>
<?= $this->endSection() ?>
