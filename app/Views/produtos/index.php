<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between mb-3">
    <p class="text-secondary mb-0">Cadastro de produtos (ingredientes e insumos) para usar nas receitas.</p>
    <a href="/produtos/novo" class="btn btn-soft-action">Novo produto</a>
</div>

<form class="card mb-3 p-3" method="get" action="<?= esc(base_url('produtos')) ?>" id="produtos-filters-form"
    data-lista-ajax="<?= esc(base_url('produtos/lista-ajax'), 'attr') ?>"
    data-produtos-index="<?= esc(base_url('produtos'), 'attr') ?>">
    <div class="row g-2">
        <div class="col-md-5">
            <input type="text" class="form-control" id="produtos-filter-q" name="q" placeholder="Buscar nome, embalagem ou categoria" value="<?= esc($filters['q'] ?? '') ?>">
        </div>
        <div class="col-md-5">
            <select class="form-select" name="categoria_id" id="produtos-filter-cat">
                <option value="">Todas as categorias</option>
                <?php foreach (($categorias ?? []) as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (string) ($filters['categoria_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
                        <?= esc($c['nome']) ?>
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
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Embalagem</th>
                    <th>Preco (pacote)</th>
                    <th>Qtd. no pacote</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody id="produtos-table-body">
                <?= view('produtos/_table_body', ['produtos' => $produtos ?? []]) ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (isset($pager)): ?>
    <div class="mt-3" id="produtos-pagination">
        <?= $pager->links() ?>
    </div>
<?php endif; ?>

<script>
(() => {
    const filtersForm = document.getElementById('produtos-filters-form');
    const filterQ = document.getElementById('produtos-filter-q');
    const filterCat = document.getElementById('produtos-filter-cat');
    const tableBody = document.getElementById('produtos-table-body');
    const paginationBox = document.getElementById('produtos-pagination');
    let ajaxTimer = null;

    const buildAjaxUrl = (paginationLink = null) => {
        const listaAjax = filtersForm?.getAttribute('data-lista-ajax') || '';
        const target = new URL(listaAjax || '/produtos/lista-ajax', window.location.href);
        const params = new URLSearchParams(new FormData(filtersForm));
        if (!paginationLink) {
            params.delete('page');
        } else {
            const fromLink = new URL(paginationLink.href);
            const page = fromLink.searchParams.get('page');
            if (page) {
                params.set('page', page);
            }
        }
        params.forEach((value, key) => {
            if (value === '') {
                target.searchParams.delete(key);
            } else {
                target.searchParams.set(key, value);
            }
        });
        return target;
    };

    const syncBrowserUrl = (ajaxUrl) => {
        const indexBase = filtersForm?.getAttribute('data-produtos-index') || '';
        const browserUrl = new URL(indexBase || '/produtos', window.location.href);
        ajaxUrl.searchParams.forEach((value, key) => {
            if (value === '') {
                browserUrl.searchParams.delete(key);
            } else {
                browserUrl.searchParams.set(key, value);
            }
        });
        window.history.replaceState({}, '', browserUrl.pathname + browserUrl.search);
    };

    const fetchProdutos = async (paginationLink = null) => {
        const ajaxUrl = buildAjaxUrl(paginationLink);
        tableBody.classList.add('opacity-50');
        try {
            const response = await fetch(ajaxUrl.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('Falha ao carregar');
            const data = await response.json();
            tableBody.innerHTML = data.tableBodyHtml || '';
            if (paginationBox) {
                paginationBox.innerHTML = data.paginationHtml || '';
            }
            syncBrowserUrl(ajaxUrl);
        } catch (e) {
            console.error(e);
        } finally {
            tableBody.classList.remove('opacity-50');
        }
    };

    filtersForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchProdutos();
    });
    filterQ?.addEventListener('input', () => {
        clearTimeout(ajaxTimer);
        ajaxTimer = setTimeout(() => fetchProdutos(), 280);
    });
    filterCat?.addEventListener('change', () => fetchProdutos());
    paginationBox?.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link || link.getAttribute('href') === '#' || link.getAttribute('href') === '') return;
        e.preventDefault();
        fetchProdutos(link);
    });
})();
</script>
<?= $this->endSection() ?>
