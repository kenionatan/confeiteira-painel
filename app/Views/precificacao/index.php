<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <div class="d-flex justify-content-between mb-3">
        <p class="text-secondary mb-0">Gerencie a formacao de preco dos produtos.</p>
        <button type="button" class="btn btn-soft-action" id="btn-open-modal-recipe">Novo item</button>
    </div>
    <form class="card mb-3 p-3" method="get" action="/precificacao" id="precificacao-filters-form">
        <div class="row g-2">
            <div class="col-md-5">
                <input type="text" class="form-control" name="q" id="precificacao-filter-q" placeholder="Buscar produto/categoria" value="<?= esc($filters['q'] ?? '') ?>">
            </div>
            <div class="col-md-5">
                <select class="form-select" name="receita_id" id="precificacao-filter-receita">
                    <option value="">Todas as receitas</option>
                    <?php foreach (($receitas ?? []) as $receita): ?>
                        <option value="<?= esc($receita['id']) ?>" <?= (string) ($filters['receita_id'] ?? '') === (string) $receita['id'] ? 'selected' : '' ?>>
                            <?= esc($receita['nome_produto']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-soft-action w-100" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Custo</th>
                        <th>Margem (%)</th>
                        <th>Preco sugerido</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                </thead>
                <tbody id="precificacao-table-body">
                    <?= view('precificacao/_table_body', ['itens' => $itens ?? []]) ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (isset($pager)): ?>
        <div class="mt-3" id="precificacao-pagination">
            <?= $pager->links() ?>
        </div>
    <?php endif; ?>

    <div class="modal-backdrop-custom" id="modal-recipe-backdrop" role="presentation" aria-hidden="true">
        <div class="card modal-card-custom" role="dialog" aria-modal="true" aria-labelledby="modal-recipe-title" id="modal-recipe-dialog">
            <div class="card-header d-flex justify-content-between align-items-center sticky-top">
                <h2 class="card-title m-0" id="modal-recipe-title">Receita</h2>
                <button type="button" class="btn btn-sm btn-icon-soft" id="modal-recipe-close" aria-label="Fechar">x</button>
            </div>
            <form id="form-recipe" class="card-body" method="post" action="/precificacao/salvar">
                <?= csrf_field() ?>
                <input type="hidden" id="modal-item-id" value="">
                <label class="form-label">Nome do item</label>
                <input type="text" name="nome_produto" id="modal-recipe-name" class="form-control mb-3" maxlength="120" autocomplete="off" required />

                <h3 class="h4 mt-2">Ingredientes</h3>
                <p class="text-secondary">Tipo de embalagem e unidades (g/kg, mL/L, unidades).</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Produto cad.</th>
                                <th>Nome</th>
                                <th>Embalagem</th>
                                <th>Preco (R$)</th>
                                <th>Qtd. no pacote</th>
                                <th>Un.</th>
                                <th>Qtd. receita</th>
                                <th>Un.</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="modal-ing-body"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-soft-action btn-sm mb-3" id="modal-btn-add-ing">+ Adicionar ingrediente</button>
                <div class="d-flex justify-content-between mb-4">
                    <span>Custo total dos ingredientes</span>
                    <strong id="modal-total-ing">R$ 0,00</strong>
                </div>

                <h3 class="h4">Mao de obra</h3>
                <p class="text-secondary small mb-2">Horas e valor da hora são opcionais; deixe em branco se não houver custo de mão de obra.</p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Horas trabalhadas <span class="text-secondary">(opcional)</span></label>
                        <input type="text" name="horas_trabalhadas" id="modal-hours" class="form-control js-labor-qty" value="" placeholder="0" autocomplete="off" inputmode="decimal" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Valor da hora (R$) <span class="text-secondary">(opcional)</span></label>
                        <input type="text" name="valor_hora" id="modal-hour-rate" class="form-control js-labor-brl" value="" placeholder="0,00" autocomplete="off" inputmode="decimal" />
                    </div>
                </div>
                <div class="d-flex justify-content-between mb-4">
                    <span>Custo da mao de obra</span>
                    <strong id="modal-total-labor">R$ 0,00</strong>
                </div>

                <h3 class="h4">Precificacao</h3>
                <div class="mb-3">
                    <label class="form-label">Margem de lucro desejada (%)</label>
                    <input type="text" name="margem_percentual" id="modal-margin" class="form-control js-qty" value="30" inputmode="decimal" />
                </div>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Custo total (receita)</span>
                        <strong id="modal-total-cost">R$ 0,00</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Preco de venda sugerido</span>
                        <strong id="modal-sale-price">R$ 0,00</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Lucro estimado</span>
                        <strong id="modal-profit">R$ 0,00</strong>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-soft-action" id="btn-save-recipe">Salvar receita</button>
                    <button type="button" class="btn btn-icon-soft-text" id="btn-modal-recipe-cancel">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal-backdrop-custom {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            z-index: 1050;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-backdrop-custom.is-open { display: flex; }
        .modal-card-custom {
            width: min(1200px, 96vw);
            max-height: 92vh;
            overflow: auto;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (() => {
            const produtosCatalogo = <?= json_encode($produtos_catalogo ?? [], JSON_UNESCAPED_UNICODE) ?>;
            let itensMap = <?= json_encode(array_column($itens ?? [], null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
            const filtersForm = document.getElementById('precificacao-filters-form');
            const filterQ = document.getElementById('precificacao-filter-q');
            const filterReceita = document.getElementById('precificacao-filter-receita');
            const tableBody = document.getElementById('precificacao-table-body');
            const paginationBox = document.getElementById('precificacao-pagination');
            const modal = document.getElementById('modal-recipe-backdrop');
            const openBtn = document.getElementById('btn-open-modal-recipe');
            const closeBtn = document.getElementById('modal-recipe-close');
            const cancelBtn = document.getElementById('btn-modal-recipe-cancel');
            const formRecipe = document.getElementById('form-recipe');
            const modalTitle = document.getElementById('modal-recipe-title');
            const saveBtn = document.getElementById('btn-save-recipe');
            const modalItemId = document.getElementById('modal-item-id');
            const recipeNameInput = document.getElementById('modal-recipe-name');
            const addIngBtn = document.getElementById('modal-btn-add-ing');
            const ingBody = document.getElementById('modal-ing-body');
            const hoursInput = document.getElementById('modal-hours');
            const hourRateInput = document.getElementById('modal-hour-rate');
            const marginInput = document.getElementById('modal-margin');

            const totalIngEl = document.getElementById('modal-total-ing');
            const totalLaborEl = document.getElementById('modal-total-labor');
            const totalCostEl = document.getElementById('modal-total-cost');
            const salePriceEl = document.getElementById('modal-sale-price');
            const profitEl = document.getElementById('modal-profit');
            let ajaxTimer = null;

            const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
            const num = (value) => {
                if (value == null) return 0;
                let s = String(value).trim().replace(/\s/g, '');
                if (s === '') return 0;
                const lastComma = s.lastIndexOf(',');
                if (lastComma !== -1) {
                    const intPart = s.slice(0, lastComma).replace(/\./g, '');
                    const fracPart = s.slice(lastComma + 1).replace(/\./g, '');
                    s = intPart + '.' + fracPart;
                } else {
                    s = s.replace(/\./g, '');
                }
                const parsed = parseFloat(s);
                return Number.isFinite(parsed) ? parsed : 0;
            };

            function normalizeUnit(value, unit) {
                const u = String(unit || '').toLowerCase();
                if (u === 'kg') return { kind: 'mass', amount: value * 1000 };
                if (u === 'g') return { kind: 'mass', amount: value };
                if (u === 'l') return { kind: 'vol', amount: value * 1000 };
                if (u === 'ml') return { kind: 'vol', amount: value };
                if (u === 'un') return { kind: 'count', amount: value };
                return { kind: 'invalid', amount: 0 };
            }

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
                el.addEventListener('blur', () => {
                    const n = num(el.value);
                    el.value = n.toLocaleString('pt-BR', { maximumFractionDigits: 6 });
                });
            }

            /** Mão de obra: pode ficar em branco; não forçar zero no blur */
            function bindOptionalQtyMask(el) {
                el.addEventListener('input', () => {
                    let s = String(el.value).replace(/[^\d.,]/g, '');
                    const c = s.indexOf(',');
                    if (c !== -1) s = s.slice(0, c + 1) + s.slice(c + 1).replace(/,/g, '');
                    el.value = s;
                });
                el.addEventListener('blur', () => {
                    if (!String(el.value).trim()) return;
                    const n = num(el.value);
                    el.value = n.toLocaleString('pt-BR', { maximumFractionDigits: 6 });
                });
            }

            function bindOptionalMoneyMask(el) {
                el.addEventListener('input', () => {
                    const digits = String(el.value).replace(/\D/g, '');
                    if (!digits) { el.value = ''; return; }
                    el.value = (parseInt(digits, 10) / 100).toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                });
                el.addEventListener('blur', () => {
                    if (!String(el.value).trim()) return;
                    const digits = String(el.value).replace(/\D/g, '');
                    if (!digits) { el.value = ''; return; }
                    el.value = (parseInt(digits, 10) / 100).toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                });
            }

            function escapeHtml(s) {
                return String(s ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function buildProdutoSelectHtml() {
                const opts = ['<option value="">— Manual —</option>'];
                (produtosCatalogo || []).forEach((p) => {
                    const label = (p.categoria_nome ? `${p.nome} (${p.categoria_nome})` : p.nome) || '';
                    opts.push(`<option value="${escapeHtml(String(p.id))}">${escapeHtml(label)}</option>`);
                });
                return opts.join('');
            }

            function applyProdutoToRow(tr, p) {
                if (!p) return;
                tr.querySelector('.js-ing-nome').value = p.nome || '';
                tr.querySelector('.js-ing-embalagem').value = p.embalagem || '';
                tr.querySelector('.js-preco').value = (Number(p.preco || 0)).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const qEmb = p.qtd_embalagem != null ? String(p.qtd_embalagem).replace('.', ',') : '0';
                tr.querySelector('.js-qtd-emb').value = qEmb;
                tr.querySelector('.js-un-emb').value = p.un_embalagem || 'g';
            }

            /**
             * PHP: ingredientes[][a] + ingredientes[][b] cria dois indices — cada campo virava uma linha.
             * Antes do submit, renomeia para ingredientes[0][nome], ingredientes[0][preco], ...
             */
            function reindexIngredientRows() {
                ingBody.querySelectorAll('tr').forEach((tr, i) => {
                    tr.querySelectorAll('input[name^="ingredientes"], select[name^="ingredientes"]').forEach((el) => {
                        const name = el.getAttribute('name');
                        if (!name) return;
                        const m = name.match(/^ingredientes\[[^\]]*\]\[([^\]]+)\]$/);
                        if (m) {
                            el.setAttribute('name', `ingredientes[${i}][${m[1]}]`);
                        }
                    });
                });
            }

            function newIngredientRow() {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="min-width:200px">
                        <select class="form-select form-select-sm js-produto-preset">${buildProdutoSelectHtml()}</select>
                        <input type="hidden" class="js-ing-pid" name="ingredientes[][produto_id]" value="">
                    </td>
                    <td><input type="text" class="form-control form-control-sm js-ing-nome" name="ingredientes[][nome]" required autocomplete="off"></td>
                    <td><input type="text" class="form-control form-control-sm js-ing-embalagem" name="ingredientes[][embalagem]" placeholder="Ex.: pacote" autocomplete="off"></td>
                    <td><input type="text" class="form-control form-control-sm js-preco js-brl" name="ingredientes[][preco]" value="0,00" inputmode="decimal" autocomplete="off"></td>
                    <td><input type="text" class="form-control form-control-sm js-qtd-emb js-qty" name="ingredientes[][qtd_embalagem]" value="0" inputmode="decimal" autocomplete="off"></td>
                    <td>
                        <select class="form-select form-select-sm js-un-emb" name="ingredientes[][un_embalagem]">
                            <option value="g">g</option><option value="kg">kg</option><option value="ml">mL</option><option value="l">L</option><option value="un">un</option>
                        </select>
                    </td>
                    <td><input type="text" class="form-control form-control-sm js-qtd-rec js-qty" name="ingredientes[][qtd_receita]" value="0" inputmode="decimal" autocomplete="off"></td>
                    <td>
                        <select class="form-select form-select-sm js-un-rec" name="ingredientes[][un_receita]">
                            <option value="g">g</option><option value="kg">kg</option><option value="ml">mL</option><option value="l">L</option><option value="un">un</option>
                        </select>
                    </td>
                    <td class="js-subtotal">R$ 0,00</td>
                    <td><button type="button" class="btn btn-sm btn-icon-soft-danger js-del" title="Remover ingrediente"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg></button></td>
                `;
                ingBody.appendChild(tr);
                tr.querySelectorAll('.js-brl').forEach(bindMoneyMask);
                tr.querySelectorAll('.js-qty').forEach(bindQtyMask);
                const sel = tr.querySelector('.js-produto-preset');
                const hid = tr.querySelector('.js-ing-pid');
                sel.addEventListener('change', () => {
                    const id = sel.value;
                    hid.value = id || '';
                    const p = (produtosCatalogo || []).find((x) => String(x.id) === String(id));
                    if (p) applyProdutoToRow(tr, p);
                    recalc();
                });
            }

            function clearRows() {
                ingBody.innerHTML = '';
            }

            function recalc() {
                let totalIng = 0;
                ingBody.querySelectorAll('tr').forEach((tr) => {
                    const preco = num(tr.querySelector('.js-preco')?.value);
                    const qtdEmb = num(tr.querySelector('.js-qtd-emb')?.value);
                    const qtdRec = num(tr.querySelector('.js-qtd-rec')?.value);
                    const unEmb = tr.querySelector('.js-un-emb')?.value;
                    const unRec = tr.querySelector('.js-un-rec')?.value;
                    const p = normalizeUnit(qtdEmb, unEmb);
                    const r = normalizeUnit(qtdRec, unRec);
                    let subtotal = 0;
                    if (p.kind !== 'invalid' && r.kind !== 'invalid' && p.kind === r.kind && p.amount > 0) {
                        subtotal = (preco / p.amount) * r.amount;
                    }
                    totalIng += subtotal;
                    tr.querySelector('.js-subtotal').textContent = money(subtotal);
                });

                const labor = num(hoursInput.value) * num(hourRateInput.value);
                const totalCost = totalIng + labor;
                const margin = num(marginInput.value);
                const salePrice = totalCost + (totalCost * (margin / 100));
                const profit = salePrice - totalCost;

                totalIngEl.textContent = money(totalIng);
                totalLaborEl.textContent = money(labor);
                totalCostEl.textContent = money(totalCost);
                salePriceEl.textContent = money(salePrice);
                profitEl.textContent = money(profit);
            }

            openBtn.addEventListener('click', () => {
                modalItemId.value = '';
                formRecipe.action = '/precificacao/salvar';
                modalTitle.textContent = 'Receita - novo item';
                saveBtn.textContent = 'Salvar receita';
                recipeNameInput.value = '';
                hoursInput.value = '';
                hourRateInput.value = '';
                marginInput.value = '30';
                clearRows();
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                if (! ingBody.querySelector('tr')) newIngredientRow();
                recalc();
            });
            const closeModal = () => {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            };
            [closeBtn, cancelBtn].forEach((btn) => btn.addEventListener('click', closeModal));
            modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

            addIngBtn.addEventListener('click', () => { newIngredientRow(); recalc(); });
            ingBody.addEventListener('click', (e) => {
                const delBtn = e.target.closest('.js-del');
                if (delBtn) {
                    delBtn.closest('tr').remove();
                    recalc();
                }
            });
            ingBody.addEventListener('input', recalc);
            ingBody.addEventListener('change', recalc);
            [hoursInput, hourRateInput, marginInput].forEach((el) => {
                el.addEventListener('input', recalc);
                el.addEventListener('blur', recalc);
            });
            bindOptionalQtyMask(hoursInput);
            bindOptionalMoneyMask(hourRateInput);
            bindQtyMask(marginInput);

            formRecipe.addEventListener('submit', () => {
                reindexIngredientRows();
                if (!String(hoursInput.value).trim()) {
                    hoursInput.value = '0';
                }
                if (!String(hourRateInput.value).trim()) {
                    hourRateInput.value = '0,00';
                }
                if (!String(marginInput.value).trim()) {
                    marginInput.value = '30';
                }
            });

            function fillRowFromIngredient(ing) {
                newIngredientRow();
                const tr = ingBody.lastElementChild;
                const pid = ing.produto_id != null && ing.produto_id !== '' ? String(ing.produto_id) : '';
                const hid = tr.querySelector('.js-ing-pid');
                const sel = tr.querySelector('.js-produto-preset');
                if (hid) hid.value = pid;
                if (sel && pid && [...sel.options].some((o) => o.value === pid)) {
                    sel.value = pid;
                }
                tr.querySelector('.js-ing-nome').value = ing.nome || '';
                tr.querySelector('.js-ing-embalagem').value = ing.embalagem || '';
                tr.querySelector('.js-preco').value = (Number(ing.preco || 0)).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                tr.querySelector('.js-qtd-emb').value = String(ing.qtd_embalagem ?? '0').replace('.', ',');
                tr.querySelector('.js-un-emb').value = ing.un_embalagem || 'g';
                tr.querySelector('.js-qtd-rec').value = String(ing.qtd_receita ?? '0').replace('.', ',');
                tr.querySelector('.js-un-rec').value = ing.un_receita || 'g';
            }

            const bindEditButtons = () => {
                document.querySelectorAll('.js-edit-item').forEach((btn) => {
                    btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-item-id');
                    const item = itensMap?.[id];
                    if (!item) return;
                    modalItemId.value = String(item.id);
                    formRecipe.action = '/precificacao/atualizar/' + item.id;
                    modalTitle.textContent = 'Receita - editar item';
                    saveBtn.textContent = 'Salvar alteracoes';
                    recipeNameInput.value = item.nome_produto || '';
                    clearRows();

                    let payload = null;
                    try {
                        payload = item.observacoes ? JSON.parse(item.observacoes) : null;
                    } catch (e) {
                        payload = null;
                    }
                    const ingredientes = Array.isArray(payload?.ingredientes) ? payload.ingredientes : [];
                    if (ingredientes.length) {
                        ingredientes.forEach(fillRowFromIngredient);
                    } else {
                        newIngredientRow();
                    }
                    const hz = payload?.horas_trabalhadas;
                    const vh = payload?.valor_hora;
                    const hNum = hz !== undefined && hz !== null && String(hz).trim() !== '' ? Number(hz) : NaN;
                    const vNum = vh !== undefined && vh !== null && String(vh).trim() !== '' ? Number(vh) : NaN;
                    if (Number.isFinite(hNum) && hNum !== 0) {
                        hoursInput.value = String(hz).replace('.', ',');
                    } else {
                        hoursInput.value = '';
                    }
                    if (Number.isFinite(vNum) && vNum !== 0) {
                        hourRateInput.value = vNum.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    } else {
                        hourRateInput.value = '';
                    }
                    marginInput.value = String(item.margem_percentual ?? '30').replace('.', ',');

                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                        recalc();
                    });
                });
            };

            const fetchItens = async (pageUrl = null) => {
                const params = new URLSearchParams(new FormData(filtersForm));
                const baseUrl = pageUrl ? new URL(pageUrl, window.location.origin) : new URL('/precificacao/lista-ajax', window.location.origin);
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
                    if (!response.ok) throw new Error('Falha ao carregar precificacao');
                    const data = await response.json();
                    itensMap = data.itensMap || {};
                    tableBody.innerHTML = data.tableBodyHtml || '';
                    if (paginationBox) {
                        paginationBox.innerHTML = data.paginationHtml || '';
                    }
                    bindEditButtons();

                    const browserUrl = new URL('/precificacao', window.location.origin);
                    params.forEach((value, key) => {
                        if (value !== '') browserUrl.searchParams.set(key, value);
                    });
                    if (baseUrl.searchParams.get('page')) {
                        browserUrl.searchParams.set('page', baseUrl.searchParams.get('page'));
                    }
                    window.history.replaceState({}, '', browserUrl.toString());
                } catch (error) {
                    console.error(error);
                } finally {
                    tableBody.classList.remove('opacity-50');
                }
            };

            filtersForm?.addEventListener('submit', (e) => {
                e.preventDefault();
                fetchItens();
            });
            filterQ?.addEventListener('input', () => {
                clearTimeout(ajaxTimer);
                ajaxTimer = setTimeout(() => fetchItens(), 280);
            });
            if (window.jQuery && window.jQuery.fn.select2 && filterReceita) {
                window.jQuery(filterReceita).select2({
                    placeholder: 'Todas as receitas',
                    allowClear: true,
                    width: '100%'
                });
                window.jQuery(filterReceita).on('change', () => fetchItens());
            } else {
                filterReceita?.addEventListener('change', () => fetchItens());
            }
            paginationBox?.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (!link) return;
                e.preventDefault();
                fetchItens(link.getAttribute('href'));
            });

            bindEditButtons();
        })();
    </script>
<?= $this->endSection() ?>
