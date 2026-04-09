<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <div class="d-flex justify-content-between mb-3">
        <p class="text-secondary mb-0">Gerencie os pedidos recebidos dos clientes.</p>
        <button type="button" class="btn btn-soft-action" id="btn-open-modal-new-order">Novo pedido</button>
    </div>

    <form class="card mb-3 p-3" method="get" action="/pedidos" id="pedidos-filters-form">
        <div class="row g-2">
            <div class="col-md-5">
                <input type="text" class="form-control" name="q" id="pedidos-filter-q" placeholder="Buscar cliente ou produto" value="<?= esc($filters['q'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status" id="pedidos-filter-status">
                    <option value="">Todos os status</option>
                    <?php foreach (['novo', 'em_producao', 'finalizado', 'entregue', 'cancelado'] as $st): ?>
                        <option value="<?= esc($st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= esc($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" name="data" id="pedidos-filter-data" value="<?= esc($filters['data'] ?? '') ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Itens</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Entrega</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                </thead>
                <tbody id="pedidos-table-body">
                    <?= view('pedidos/_table_body', ['pedidos' => $pedidos ?? []]) ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="pedido-modal-backdrop" id="pedido-modal-backdrop" aria-hidden="true">
        <div class="pedido-modal card" id="pedido-modal-dialog" role="dialog" aria-modal="true">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title m-0" id="pedido-modal-title">Pedido</h3>
                <button type="button" class="btn btn-sm btn-icon-soft" id="pedido-modal-close" title="Fechar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="card-body" id="pedido-modal-content"></div>
        </div>
    </div>
    <style>
        .pedido-modal-backdrop {
            position: fixed; inset: 0; z-index: 2000;
            display: none; align-items: center; justify-content: center; padding: 1rem;
            background: rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(4px);
        }
        .pedido-modal-backdrop.is-open { display: flex; }
        .pedido-modal { width: min(1100px, 96vw); max-height: 92vh; overflow: auto; }
        .pedido-header-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .75rem; }
        @media (max-width: 900px) { .pedido-header-grid { grid-template-columns: 1fr 1fr; } }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (() => {
            let pedidos = <?= json_encode($pedidos ?? [], JSON_UNESCAPED_UNICODE) ?>;
            let itensPorPedido = <?= json_encode($itensPorPedido ?? [], JSON_UNESCAPED_UNICODE) ?>;
            const clientes = <?= json_encode($clientes ?? [], JSON_UNESCAPED_UNICODE) ?>;
            const produtos = <?= json_encode($produtos ?? [], JSON_UNESCAPED_UNICODE) ?>;
            const filtersForm = document.getElementById('pedidos-filters-form');
            const filterQ = document.getElementById('pedidos-filter-q');
            const filterStatus = document.getElementById('pedidos-filter-status');
            const filterData = document.getElementById('pedidos-filter-data');
            const tableBody = document.getElementById('pedidos-table-body');
            const paginationBox = document.getElementById('pedidos-pagination');
            const newOrderBtn = document.getElementById('btn-open-modal-new-order');
            const backdrop = document.getElementById('pedido-modal-backdrop');
            const closeBtn = document.getElementById('pedido-modal-close');
            const content = document.getElementById('pedido-modal-content');
            const title = document.getElementById('pedido-modal-title');
            let ajaxTimer = null;
            const formatBRL = (n) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(n || 0));
            const formatDateBR = (dateStr, withTime = false) => {
                if (!dateStr) return '-';
                const d = new Date(dateStr);
                if (Number.isNaN(d.getTime())) return '-';
                return withTime ? d.toLocaleString('pt-BR') : d.toLocaleDateString('pt-BR');
            };

            const open = () => { backdrop.classList.add('is-open'); backdrop.setAttribute('aria-hidden', 'false'); };
            const close = () => { backdrop.classList.remove('is-open'); backdrop.setAttribute('aria-hidden', 'true'); };
            closeBtn.addEventListener('click', close);
            backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });

            const statusOptions = ['novo','em_producao','finalizado','entregue','cancelado'];

            function renderItemsRows(items, prefix) {
                if (!Array.isArray(items) || !items.length) {
                    return `<tr><td colspan="5" class="text-secondary text-center">Nenhum item</td></tr>`;
                }
                return items.map((item, idx) => `
                    <tr data-item="1" data-subtotal="${Number(item.subtotal || 0)}">
                        <td>
                            ${item.produto_nome || '-'}
                            <input type="hidden" name="${prefix}[${idx}][produto_id]" value="${item.produto_id || ''}">
                        </td>
                        <td>
                            ${item.quantidade || 0}
                            <input type="hidden" name="${prefix}[${idx}][quantidade]" value="${item.quantidade || 0}">
                        </td>
                        <td>${formatBRL(item.valor_unitario || 0)}</td>
                        <td>${formatBRL(item.subtotal || 0)}</td>
                        <td><button type="button" class="btn btn-sm btn-icon-soft-danger js-del-item" title="Remover"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg></button></td>
                    </tr>
                `).join('');
            }

            function recalcModalTotal(container) {
                const totalEl = container.querySelector('.js-total-pedido');
                if (!totalEl) return;
                let total = 0;
                container.querySelectorAll('tr[data-item]').forEach((tr) => {
                    total += Number(tr.getAttribute('data-subtotal') || 0);
                });
                totalEl.textContent = formatBRL(total);
            }

            function renderView(pedido) {
                const items = itensPorPedido[String(pedido.id)] || [];
                title.textContent = `Pedido #${pedido.id}`;
                content.innerHTML = `
                    <div class="card mb-3">
                        <div class="card-body">
                            <h4 class="card-title">Cabecalho do pedido</h4>
                            <div class="pedido-header-grid">
                                <div><strong>Cliente:</strong> ${pedido.cliente_nome || '-'}</div>
                                <div><strong>Status:</strong> ${(pedido.status || '').replaceAll('_',' ')}</div>
                                <div><strong>Criado em:</strong> ${formatDateBR(pedido.created_at, true)}</div>
                                <div><strong>Entrega:</strong> ${formatDateBR(pedido.data_entrega, false)}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">Itens do pedido</h4>
                            <div class="table-responsive">
                                <table class="table table-vcenter card-table">
                                    <thead><tr><th>Produto</th><th>Qtd</th><th>Valor unitario</th><th>Subtotal</th></tr></thead>
                                    <tbody>
                                        ${items.length ? items.map(item => `<tr><td>${item.produto_nome || '-'}</td><td>${item.quantidade || 0}</td><td>${formatBRL(item.valor_unitario || 0)}</td><td>${formatBRL(item.subtotal || 0)}</td></tr>`).join('') : `<tr><td colspan="4" class="text-secondary text-center">Sem itens</td></tr>`}
                                    </tbody>
                                    <tfoot><tr><th colspan="3" class="text-end">Total</th><th>${formatBRL(pedido.valor_total || 0)}</th></tr></tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            }

            function renderOrderForm(mode, pedido = null) {
                const isEdit = mode === 'edit';
                const items = isEdit ? (itensPorPedido[String(pedido.id)] || []) : [];
                const actionUrl = isEdit ? `/pedidos/atualizar/${pedido.id}` : '/pedidos/salvar';
                title.textContent = isEdit ? `Editar pedido #${pedido.id}` : 'Novo pedido';
                content.innerHTML = `
                    <form method="post" action="${actionUrl}" id="modal-form-order">
                        <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h4 class="card-title">Cabecalho do pedido</h4>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Cliente</label>
                                        <select class="form-select" name="cliente_id" id="modal-cliente-select">
                                            <option value="">Selecione um cliente</option>
                                            ${clientes.map(c => `<option value="${c.id}">${c.nome}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cliente (manual)</label>
                                        <input class="form-control" name="cliente_nome" value="${isEdit ? (pedido.cliente_nome || '') : ''}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            ${statusOptions.map(s => `<option value="${s}" ${isEdit ? (pedido.status === s ? 'selected' : '') : (s === 'novo' ? 'selected' : '')}>${s}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Data entrega</label>
                                        <input type="date" class="form-control" name="data_entrega" value="${isEdit ? (pedido.data_entrega || '') : ''}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">Itens do pedido</h4>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-7">
                                        <select class="form-select" id="modal-produto-select">
                                            <option value="">Selecione um produto</option>
                                            ${produtos.map(p => `<option value="${p.id}" data-nome="${p.nome_produto}" data-preco="${Number(p.preco_sugerido || 0)}">${p.nome_produto}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-2"><input type="number" class="form-control" id="modal-item-qtd" value="1" min="1"></div>
                                    <div class="col-md-3"><button type="button" class="btn btn-soft-action w-100" id="modal-btn-add-item">Adicionar item</button></div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-vcenter card-table">
                                        <thead><tr><th>Produto</th><th>Qtd</th><th>Valor unitario</th><th>Subtotal</th><th></th></tr></thead>
                                        <tbody id="modal-itens-body">${renderItemsRows(items, 'linhas')}</tbody>
                                        <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="js-total-pedido">${formatBRL(isEdit ? (pedido.valor_total || 0) : 0)}</th><th></th></tr></tfoot>
                                    </table>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">${isEdit ? 'Salvar alteracoes' : 'Salvar pedido'}</button>
                                    <button type="button" class="btn btn-icon-soft-text" id="modal-cancel-edit">Cancelar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                `;

                const form = document.getElementById('modal-form-order');
                const itensBody = document.getElementById('modal-itens-body');
                const addBtn = document.getElementById('modal-btn-add-item');
                const produtoSel = document.getElementById('modal-produto-select');
                const qtdEl = document.getElementById('modal-item-qtd');
                const clienteSel = document.getElementById('modal-cliente-select');

                if (clienteSel && isEdit) {
                    const lowerPedidoCliente = String(pedido.cliente_nome || '').toLowerCase().trim();
                    const match = clientes.find((c) => String(c.nome || '').toLowerCase().trim() === lowerPedidoCliente);
                    if (match) {
                        clienteSel.value = String(match.id);
                    }
                }
                if (clienteSel) {
                    if (window.jQuery && window.jQuery.fn.select2) {
                        window.jQuery(clienteSel).select2({
                            placeholder: 'Selecione um cliente',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: window.jQuery('#pedido-modal-dialog')
                        });
                    }
                }
                if (produtoSel && window.jQuery && window.jQuery.fn.select2) {
                    window.jQuery(produtoSel).select2({
                        placeholder: 'Selecione um produto',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: window.jQuery('#pedido-modal-dialog')
                    });
                }

                addBtn?.addEventListener('click', () => {
                    const opt = produtoSel?.options?.[produtoSel.selectedIndex];
                    if (!opt || !opt.value) return;
                    const qtd = parseInt(qtdEl.value || '1', 10);
                    if (!Number.isFinite(qtd) || qtd <= 0) return;
                    const nome = opt.getAttribute('data-nome') || opt.textContent || '';
                    const preco = Number(opt.getAttribute('data-preco') || 0);
                    const subtotal = preco * qtd;
                    const idx = itensBody.querySelectorAll('tr[data-item]').length;
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-item', '1');
                    tr.setAttribute('data-subtotal', String(subtotal));
                    tr.innerHTML = `<td>${nome}<input type="hidden" name="linhas[${idx}][produto_id]" value="${opt.value}"></td><td>${qtd}<input type="hidden" name="linhas[${idx}][quantidade]" value="${qtd}"></td><td>${formatBRL(preco)}</td><td>${formatBRL(subtotal)}</td><td><button type="button" class="btn btn-sm btn-icon-soft-danger js-del-item" title="Remover"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg></button></td>`;
                    itensBody.appendChild(tr);
                    recalcModalTotal(content);
                });

                itensBody?.addEventListener('click', (e) => {
                    const del = e.target.closest('.js-del-item');
                    if (!del) return;
                    del.closest('tr')?.remove();
                    recalcModalTotal(content);
                });
                document.getElementById('modal-cancel-edit')?.addEventListener('click', close);
                form?.addEventListener('submit', (e) => {
                    if (!itensBody.querySelector('tr[data-item]')) {
                        e.preventDefault();
                        alert('Adicione ao menos um item.');
                    }
                });
                recalcModalTotal(content);
            }

            function renderEdit(pedido) {
                renderOrderForm('edit', pedido);
            }

            function renderCreate() {
                renderOrderForm('create');
            }

            const bindActionButtons = () => {
                document.querySelectorAll('.js-btn-view').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const id = Number(btn.getAttribute('data-id'));
                        const pedido = pedidos.find((p) => Number(p.id) === id);
                        if (!pedido) return;
                        renderView(pedido);
                        open();
                    });
                });
                document.querySelectorAll('.js-btn-edit').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const id = Number(btn.getAttribute('data-id'));
                        const pedido = pedidos.find((p) => Number(p.id) === id);
                        if (!pedido) return;
                        renderEdit(pedido);
                        open();
                    });
                });
            };

            const fetchPedidos = async (pageUrl = null, pushUrl = true) => {
                const params = new URLSearchParams(new FormData(filtersForm));
                const baseUrl = pageUrl ? new URL(pageUrl, window.location.origin) : new URL('/pedidos/lista-ajax', window.location.origin);
                if (!pageUrl) {
                    baseUrl.search = params.toString();
                } else {
                    params.forEach((value, key) => {
                        if (key !== 'page') {
                            baseUrl.searchParams.set(key, value);
                        }
                    });
                }
                tableBody.classList.add('opacity-50');
                try {
                    const response = await fetch(baseUrl.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!response.ok) throw new Error('Falha ao carregar pedidos');
                    const data = await response.json();
                    pedidos = Array.isArray(data.pedidos) ? data.pedidos : [];
                    itensPorPedido = data.itensPorPedido || {};
                    tableBody.innerHTML = data.tableBodyHtml || '';
                    if (paginationBox) {
                        paginationBox.innerHTML = data.paginationHtml || '';
                    }
                    bindActionButtons();
                    if (pushUrl) {
                        const browserUrl = new URL('/pedidos', window.location.origin);
                        params.forEach((value, key) => {
                            if (value !== '') browserUrl.searchParams.set(key, value);
                        });
                        if (baseUrl.searchParams.get('page')) {
                            browserUrl.searchParams.set('page', baseUrl.searchParams.get('page'));
                        }
                        window.history.replaceState({}, '', browserUrl.toString());
                    }
                } catch (error) {
                    console.error(error);
                } finally {
                    tableBody.classList.remove('opacity-50');
                }
            };

            filtersForm?.addEventListener('submit', (e) => {
                e.preventDefault();
                fetchPedidos();
            });

            filterStatus?.addEventListener('change', () => fetchPedidos());
            filterData?.addEventListener('change', () => fetchPedidos());
            filterQ?.addEventListener('input', () => {
                clearTimeout(ajaxTimer);
                ajaxTimer = setTimeout(() => fetchPedidos(), 280);
            });

            paginationBox?.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (!link) return;
                e.preventDefault();
                fetchPedidos(link.getAttribute('href'));
            });

            newOrderBtn?.addEventListener('click', () => {
                renderCreate();
                open();
            });

            bindActionButtons();
        })();
    </script>
    <?php if (isset($pager)): ?>
        <div class="mt-3" id="pedidos-pagination">
            <?= $pager->links() ?>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>
