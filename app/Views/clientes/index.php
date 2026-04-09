<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Novo cliente</h3>
                <form method="post" action="/painel/clientes/salvar">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" name="nome" value="<?= esc(old('nome')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" class="form-control input-phone-br" name="telefone" value="<?= esc(old('telefone')) ?>" maxlength="16" placeholder="(00) 00000-0000" inputmode="numeric">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Endereco</label>
                        <input type="text" class="form-control" name="endereco" value="<?= esc(old('endereco')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observacoes</label>
                        <textarea class="form-control" name="observacoes" rows="3"><?= esc(old('observacoes')) ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Salvar cliente</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <form class="card mb-3 p-3" method="get" action="/painel/clientes" id="clientes-filters-form">
            <div class="row g-2">
                <div class="col-md-9">
                    <input type="text" class="form-control" id="clientes-filter-q" name="q" placeholder="Buscar cliente, telefone ou endereco" value="<?= esc($filters['q'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="submit">Filtrar</button>
                </div>
            </div>
        </form>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Endereco</th>
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
<script>
    (() => {
        const filtersForm = document.getElementById('clientes-filters-form');
        const filterQ = document.getElementById('clientes-filter-q');
        const tableBody = document.getElementById('clientes-table-body');
        const paginationBox = document.getElementById('clientes-pagination');
        let ajaxTimer = null;

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

        const input = document.querySelector('.input-phone-br');
        if (!input) return;
        const formatPhoneBR = (digits) => {
            const d = String(digits).replace(/\D/g, '').slice(0, 11);
            if (!d.length) return '';
            if (d.length <= 2) return '(' + d;
            if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
            if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6, 10);
            return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7, 11);
        };
        input.addEventListener('input', () => {
            input.value = formatPhoneBR(input.value);
        });
        input.addEventListener('blur', () => {
            input.value = formatPhoneBR(input.value);
        });
    })();
</script>
<?= $this->endSection() ?>

