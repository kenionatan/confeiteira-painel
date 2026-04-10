<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-12">
        <form class="card mb-3 p-3" method="get" action="/painel/clientes" id="clientes-filters-form">
            <div class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label class="form-label" for="clientes-filter-q">Buscar</label>
                    <input type="text" class="form-control" id="clientes-filter-q" name="q" placeholder="Nome, e-mail, WhatsApp ou domínio" value="<?= esc($filters['q'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="submit">Filtrar</button>
                </div>
            </div>
        </form>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Domínio</th>
                            <th>E-mail</th>
                            <th>WhatsApp</th>
                            <th>Cartão</th>
                            <th>Cadastro</th>
                        </tr>
                    </thead>
                    <tbody id="clientes-table-body">
                        <?= view('clientes/_table_body', ['clientes' => $clientes ?? []]) ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (isset($pager)): ?>
            <div class="mt-3" id="clientes-pagination">
                <?= $pager->links() ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal modal-blur fade" id="cliente-detalhe-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cliente-detalhe-modal-title">Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="cliente-detalhe-loading" class="text-secondary py-4 text-center d-none">Carregando…</div>
                <div id="cliente-detalhe-erro" class="alert alert-danger d-none"></div>
                <div id="cliente-detalhe-conteudo" class="d-none">
                    <h6 class="text-secondary text-uppercase fs-6 mb-2">Dados do cliente</h6>
                    <dl class="row mb-4" id="cliente-detalhe-bloco-cliente"></dl>
                    <h6 class="text-secondary text-uppercase fs-6 mb-2">Assinatura / plano</h6>
                    <div id="cliente-detalhe-bloco-plano" class="mb-4"></div>
                    <h6 class="text-secondary text-uppercase fs-6 mb-2">Pagamentos (mensalidade)</h6>
                    <p class="text-secondary small mb-2" id="cliente-detalhe-pagamentos-ajuda">Registros vindos do Stripe quando <code>invoice.paid</code> é processado no webhook <code>/webhooks/stripe</code>.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-vcenter">
                            <thead>
                                <tr>
                                    <th>Período</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Pago em</th>
                                    <th>Fatura</th>
                                </tr>
                            </thead>
                            <tbody id="cliente-detalhe-tbody-pagamentos"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (() => {
        const money = (n, cur) => {
            const c = (cur || 'BRL').toUpperCase();
            try {
                return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: c }).format(Number(n));
            } catch {
                return String(n) + ' ' + c;
            }
        };
        const esc = (s) => {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        };
        const subStatusPt = {
            trial: 'Trial',
            active: 'Ativa',
            past_due: 'Em atraso',
            cancelled: 'Cancelada',
        };
        const payStatusPt = {
            pending: 'Pendente',
            paid: 'Pago',
            failed: 'Falhou',
            refunded: 'Estornado',
        };

        const filtersForm = document.getElementById('clientes-filters-form');
        const filterQ = document.getElementById('clientes-filter-q');
        const tableBody = document.getElementById('clientes-table-body');
        const paginationBox = document.getElementById('clientes-pagination');
        let ajaxTimer = null;

        const modalEl = document.getElementById('cliente-detalhe-modal');
        const modalTitle = document.getElementById('cliente-detalhe-modal-title');
        const loadingEl = document.getElementById('cliente-detalhe-loading');
        const erroEl = document.getElementById('cliente-detalhe-erro');
        const conteudoEl = document.getElementById('cliente-detalhe-conteudo');
        const blocoCliente = document.getElementById('cliente-detalhe-bloco-cliente');
        const blocoPlano = document.getElementById('cliente-detalhe-bloco-plano');
        const tbodyPag = document.getElementById('cliente-detalhe-tbody-pagamentos');

        const resetModal = () => {
            loadingEl.classList.add('d-none');
            erroEl.classList.add('d-none');
            erroEl.textContent = '';
            conteudoEl.classList.add('d-none');
            blocoCliente.innerHTML = '';
            blocoPlano.innerHTML = '';
            tbodyPag.innerHTML = '';
        };

        const openClienteModal = async (id) => {
            if (!id) return;
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            resetModal();
            loadingEl.classList.remove('d-none');
            modalTitle.textContent = 'Cliente';
            modal.show();

            try {
                const res = await fetch('/painel/clientes/' + encodeURIComponent(id) + '/detalhes', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                loadingEl.classList.add('d-none');
                if (!res.ok) {
                    erroEl.textContent = data.error || 'Não foi possível carregar os dados.';
                    erroEl.classList.remove('d-none');
                    return;
                }

                const c = data.cliente || {};
                modalTitle.textContent = c.nome ? String(c.nome) : 'Cliente';

                const cardLine = (label, val) =>
                    '<dt class="col-sm-4 text-secondary">' + esc(label) + '</dt><dd class="col-sm-8">' + (val === '' || val == null ? '<span class="text-secondary">—</span>' : val) + '</dd>';

                let cartao = '—';
                const last4 = c.cartao_ultimos4 || '';
                const brand = c.cartao_bandeira || '';
                if (last4 && last4 !== '0000') {
                    cartao = esc((brand ? String(brand).toUpperCase() + ' ' : '') + '•••• ' + last4);
                }

                blocoCliente.innerHTML =
                    cardLine('Domínio', esc(c.dominio || '')) +
                    cardLine('E-mail', esc(c.email || '')) +
                    cardLine('WhatsApp', esc(c.whatsapp || '')) +
                    cardLine('Stripe Customer', esc(c.stripe_customer_id || '')) +
                    cardLine('Cartão', cartao) +
                    cardLine('Cadastro', esc(c.created_at || ''));

                const sub = data.subscription;
                if (!sub) {
                    blocoPlano.innerHTML = '<p class="text-secondary mb-0">Nenhuma assinatura registrada.</p>';
                } else {
                    const st = subStatusPt[sub.status] || sub.status;
                    blocoPlano.innerHTML =
                        '<dl class="row mb-0">' +
                        cardLine('Plano', esc(sub.plan_nome || '') + (sub.plan_slug ? ' <span class="badge bg-secondary-lt">' + esc(sub.plan_slug) + '</span>' : '')) +
                        cardLine('Valor mensal (tabela)', money(sub.plan_valor_mensal || 0, 'BRL')) +
                        cardLine('Status', esc(st)) +
                        cardLine('Próxima cobrança', esc(sub.next_billing_at || '—')) +
                        cardLine('Assinatura Stripe', esc(sub.gateway_subscription_id || '—')) +
                        '</dl>';
                }

                const pays = data.payments || [];
                if (pays.length === 0) {
                    tbodyPag.innerHTML = '<tr><td colspan="5" class="text-secondary text-center">Nenhum pagamento registrado ainda.</td></tr>';
                } else {
                    tbodyPag.innerHTML = pays.map((p) => {
                        const st = payStatusPt[p.status] || p.status;
                        const ini = p.period_start || '—';
                        const fim = p.period_end || '—';
                        const periodo = esc(ini) + ' → ' + esc(fim);
                        const inv = p.gateway_invoice_id ? '<code class="user-select-all small">' + esc(p.gateway_invoice_id) + '</code>' : '—';
                        return '<tr><td>' + periodo + '</td><td>' + money(p.amount, p.currency) + '</td><td>' + esc(st) + '</td><td>' + esc(p.paid_at || '—') + '</td><td>' + inv + '</td></tr>';
                    }).join('');
                }

                conteudoEl.classList.remove('d-none');
            } catch (e) {
                loadingEl.classList.add('d-none');
                erroEl.textContent = 'Erro de rede ao carregar detalhes.';
                erroEl.classList.remove('d-none');
            }
        };

        tableBody?.addEventListener('click', (e) => {
            const tr = e.target.closest('tr[data-cliente-id]');
            if (!tr) return;
            const id = tr.getAttribute('data-cliente-id');
            openClienteModal(id);
        });
        tableBody?.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const tr = e.target.closest('tr[data-cliente-id]');
            if (!tr) return;
            e.preventDefault();
            openClienteModal(tr.getAttribute('data-cliente-id'));
        });

        const fetchClientes = async (pageUrl = null) => {
            const params = new URLSearchParams(new FormData(filtersForm));
            const baseUrl = pageUrl ? new URL(pageUrl, window.location.origin) : new URL('/painel/clientes/lista-ajax', window.location.origin);
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
                if (!response.ok) throw new Error('Falha ao carregar clientes');
                const data = await response.json();
                tableBody.innerHTML = data.tableBodyHtml || '';
                if (paginationBox) {
                    paginationBox.innerHTML = data.paginationHtml || '';
                }

                const browserUrl = new URL('/painel/clientes', window.location.origin);
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
            fetchClientes();
        });
        filterQ?.addEventListener('input', () => {
            clearTimeout(ajaxTimer);
            ajaxTimer = setTimeout(() => fetchClientes(), 280);
        });
        paginationBox?.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (!link) return;
            e.preventDefault();
            fetchClientes(link.getAttribute('href'));
        });
    })();
</script>
<?= $this->endSection() ?>
