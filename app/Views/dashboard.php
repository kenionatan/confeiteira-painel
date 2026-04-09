<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .card-kpi-pedidos {
            position: relative;
            overflow: visible;
        }
        .card-kpi-pedidos .chartchar-corner {
            position: absolute;
            top: -98px;
            right: .75rem;
            width: 118px;
            height: 164px;
            background-image: url('/images/chartchar.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: .96;
            pointer-events: none;
            z-index: 4;
        }
        .card-kpi-pedidos .card-body {
            padding-top: 2.95rem;
        }
    </style>
    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-kpi-pedidos">
                <span class="chartchar-corner" aria-hidden="true"></span>
                <div class="card-body">
                    <div class="subheader">Pedidos</div>
                    <div class="h1 mb-0"><?= esc($totalPedidos ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Faturamento</div>
                    <div class="h1 mb-0">R$ <?= number_format((float) ($faturamento ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Clientes</div>
                    <div class="h1 mb-0"><?= esc($totalClientes ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Receitas</div>
                    <div class="h1 mb-0"><?= esc($totalReceitas ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Custo estimado</div>
                    <div class="h1 mb-0">R$ <?= number_format((float) ($custoEstimado ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Lucro estimado</div>
                    <div class="h1 mb-0">R$ <?= number_format((float) ($lucroEstimado ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Ticket medio</div>
                    <div class="h1 mb-0">R$ <?= number_format((float) ($ticketMedio ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Pendentes</div>
                    <div class="h1 mb-0"><?= esc($pedidosPendentes ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="card-title m-0">Analise por receita</h3>
                <div class="d-flex gap-2 flex-wrap">
                    <div style="min-width:220px;">
                        <select id="dashboard-analysis-mode" class="form-select">
                            <option value="precificacao" selected>Precificacao</option>
                            <option value="receita">Analise por receita</option>
                        </select>
                    </div>
                    <div style="min-width:280px;" id="dashboard-receita-wrap" class="d-none">
                        <select id="dashboard-receita-select" class="form-select">
                            <option value="">Todas as receitas</option>
                            <?php foreach (($receitasOptions ?? []) as $receita): ?>
                                <option value="<?= esc($receita['id']) ?>" <?= (int) ($receitaInicialId ?? 0) === (int) $receita['id'] ? 'selected' : '' ?>>
                                    <?= esc($receita['nome_produto']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row row-deck row-cards mb-2">
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="subheader">Pedidos da receita</div>
                            <div class="h2 mb-0" id="receita-total-pedidos">0</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="subheader">Qtd total vendida</div>
                            <div class="h2 mb-0" id="receita-total-qtd">0</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="subheader">Faturamento</div>
                            <div class="h2 mb-0" id="receita-faturamento">R$ 0,00</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="subheader">Lucro estimado</div>
                            <div class="h2 mb-0" id="receita-lucro">R$ 0,00</div>
                            <small class="text-secondary" id="receita-margem">Margem 0%</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row row-cards">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">Faturamento da receita (30 dias)</h3>
                            <canvas id="receitaDailyChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">Status para a receita</h3>
                            <canvas id="receitaStatusChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Pedidos (7 dias)</div>
                    <div class="h2 mb-1"><?= esc($ordersLast7 ?? 0) ?></div>
                    <p class="text-secondary mb-0">Novos pedidos na ultima semana</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Faturamento (7 dias)</div>
                    <div class="h2 mb-1">R$ <?= number_format((float) ($revenueLast7 ?? 0), 2, ',', '.') ?></div>
                    <p class="text-secondary mb-0">Entradas recentes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Modulos</h3>
                    <div class="d-grid gap-2">
                        <a href="/pedidos" class="btn btn-soft-action">Pedidos</a>
                        <a href="/precificacao" class="btn btn-icon-soft-text">Precificacao</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Top produtos</h3>
                    <ul class="list-group list-group-flush">
                        <?php foreach (($topProdutos ?? []) as $item): ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><?= esc($item['produto']) ?></span>
                                <strong><?= esc($item['total_qtd']) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Faturamento (ultimos 30 dias)</h3>
                    <canvas id="dailyChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Pedidos por dia (ultimos 30 dias)</h3>
                    <canvas id="ordersChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Faturamento mensal (6 meses)</h3>
                    <canvas id="monthlyChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Pedidos por status</h3>
                    <canvas id="statusChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const formatBRL = (n) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(n || 0));
        const statusCtx = document.getElementById('statusChart');
        const dailyCtx = document.getElementById('dailyChart');
        const ordersCtx = document.getElementById('ordersChart');
        const monthlyCtx = document.getElementById('monthlyChart');
        const receitaSelect = document.getElementById('dashboard-receita-select');
        const analysisModeSelect = document.getElementById('dashboard-analysis-mode');
        const receitaWrap = document.getElementById('dashboard-receita-wrap');
        const receitaStats = <?= json_encode($receitaStats ?? [], JSON_UNESCAPED_UNICODE) ?>;
        const precStats = <?= json_encode($precStats ?? [], JSON_UNESCAPED_UNICODE) ?>;
        const precReceitasMap = <?= json_encode($precReceitasMap ?? [], JSON_UNESCAPED_UNICODE) ?>;
        const receitaDailyCtx = document.getElementById('receitaDailyChart');
        const receitaStatusCtx = document.getElementById('receitaStatusChart');
        let receitaDailyChart = null;
        let receitaStatusChart = null;
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statusLabels ?? []) ?>,
                datasets: [{ data: <?= json_encode($statusValues ?? []) ?>, backgroundColor: ['#7c6cf0','#e84d6b','#2eb87a','#f0a04b','#45b7d1','#c4c4c4'] }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dailyLabels ?? []) ?>,
                datasets: [{
                    label: 'R$ faturamento',
                    data: <?= json_encode($dailyValues ?? []) ?>,
                    fill: false,
                    borderWidth: 2,
                    borderColor: '#4f8cff'
                }]
            }
        });
        new Chart(ordersCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($dailyOrderLabels ?? []) ?>,
                datasets: [{
                    label: 'Pedidos',
                    data: <?= json_encode($dailyOrderValues ?? []) ?>,
                    borderRadius: 8,
                    backgroundColor: '#2eb87a'
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthlyLabels ?? []) ?>,
                datasets: [{
                    label: 'R$ faturamento',
                    data: <?= json_encode($monthlyValues ?? []) ?>,
                    borderRadius: 8,
                    backgroundColor: '#7c6cf0'
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });

        const renderReceitaSection = (receitaId) => {
            const fallback = {
                totalPedidos: 0,
                totalQtd: 0,
                faturamento: 0,
                lucro: 0,
                margem: 0,
                dailyLabels: [new Date().toISOString().slice(0, 10)],
                dailyValues: [0],
                statusLabels: ['Sem dados'],
                statusValues: [1],
            };
            const stats = receitaStats?.[String(receitaId)] ?? fallback;
            document.getElementById('receita-total-pedidos').textContent = Number(stats.totalPedidos || 0).toLocaleString('pt-BR');
            document.getElementById('receita-total-qtd').textContent = Number(stats.totalQtd || 0).toLocaleString('pt-BR');
            document.getElementById('receita-faturamento').textContent = formatBRL(stats.faturamento || 0);
            document.getElementById('receita-lucro').textContent = formatBRL(stats.lucro || 0);
            document.getElementById('receita-margem').textContent = `Margem ${Number(stats.margem || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;

            if (receitaDailyChart) receitaDailyChart.destroy();
            if (receitaStatusChart) receitaStatusChart.destroy();

            receitaDailyChart = new Chart(receitaDailyCtx, {
                type: 'line',
                data: {
                    labels: stats.dailyLabels?.length ? stats.dailyLabels : fallback.dailyLabels,
                    datasets: [{
                        label: 'R$ faturamento',
                        data: stats.dailyValues?.length ? stats.dailyValues : fallback.dailyValues,
                        borderColor: '#4f8cff',
                        borderWidth: 2,
                        fill: false
                    }]
                }
            });

            receitaStatusChart = new Chart(receitaStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: stats.statusLabels?.length ? stats.statusLabels : fallback.statusLabels,
                    datasets: [{
                        data: stats.statusValues?.length ? stats.statusValues : fallback.statusValues,
                        backgroundColor: ['#7c6cf0','#e84d6b','#2eb87a','#f0a04b','#45b7d1','#c4c4c4']
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        };

        const renderPrecificacaoSection = (receitaId = '') => {
            const hasSelected = String(receitaId || '') !== '';
            const receitaPrec = hasSelected ? (precReceitasMap[String(receitaId)] || null) : null;

            document.getElementById('receita-total-pedidos').textContent = hasSelected ? '1' : Number(precStats.totalReceitas || 0).toLocaleString('pt-BR');
            document.getElementById('receita-total-qtd').textContent = formatBRL(hasSelected ? (receitaPrec?.custo || 0) : (precStats.custoMedio || 0));
            document.getElementById('receita-faturamento').textContent = formatBRL(hasSelected ? (receitaPrec?.preco || 0) : (precStats.precoMedio || 0));
            document.getElementById('receita-lucro').textContent = formatBRL(hasSelected ? (receitaPrec?.ganho || 0) : ((precStats.precoMedio || 0) - (precStats.custoMedio || 0)));
            document.getElementById('receita-margem').textContent = hasSelected
                ? `Margem ${Number(receitaPrec?.margem || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`
                : `Margem media ${Number(precStats.margemMedia || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;

            const k1 = document.querySelector('#receita-total-pedidos')?.closest('.card-body')?.querySelector('.subheader');
            const k2 = document.querySelector('#receita-total-qtd')?.closest('.card-body')?.querySelector('.subheader');
            const k3 = document.querySelector('#receita-faturamento')?.closest('.card-body')?.querySelector('.subheader');
            const k4 = document.querySelector('#receita-lucro')?.closest('.card-body')?.querySelector('.subheader');
            if (k1) k1.textContent = 'Total de receitas';
            if (k2) k2.textContent = 'Custo medio';
            if (k3) k3.textContent = 'Preco medio';
            if (k4) k4.textContent = 'Ganho medio';

            const t1 = document.querySelector('#receitaDailyChart')?.closest('.card-body')?.querySelector('.card-title');
            const t2 = document.querySelector('#receitaStatusChart')?.closest('.card-body')?.querySelector('.card-title');
            if (t1) t1.textContent = 'Comparativo custo x preco';
            if (t2) t2.textContent = 'Faixa de margem';

            if (receitaDailyChart) receitaDailyChart.destroy();
            if (receitaStatusChart) receitaStatusChart.destroy();

            receitaDailyChart = new Chart(receitaDailyCtx, {
                type: 'bar',
                data: {
                    labels: hasSelected
                        ? [receitaPrec?.nome || 'Receita']
                        : ((precStats.topLabels && precStats.topLabels.length) ? precStats.topLabels : ['Sem dados']),
                    datasets: [
                        {
                            label: 'Custo',
                            data: hasSelected
                                ? [Number(receitaPrec?.custo || 0)]
                                : ((precStats.topCustoValues && precStats.topCustoValues.length) ? precStats.topCustoValues : [0]),
                            backgroundColor: '#45b7d1',
                            borderRadius: 8
                        },
                        {
                            label: 'Preco',
                            data: hasSelected
                                ? [Number(receitaPrec?.preco || 0)]
                                : ((precStats.topPrecoValues && precStats.topPrecoValues.length) ? precStats.topPrecoValues : [0]),
                            backgroundColor: '#7c6cf0',
                            borderRadius: 8
                        }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } } }
            });

            receitaStatusChart = new Chart(receitaStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: hasSelected
                        ? [`Faixa ${receitaPrec?.faixa || '-'}`]
                        : ((precStats.faixaLabels && precStats.faixaLabels.length) ? precStats.faixaLabels : ['Sem dados']),
                    datasets: [{
                        data: hasSelected
                            ? [1]
                            : ((precStats.faixaValues && precStats.faixaValues.length) ? precStats.faixaValues : [1]),
                        backgroundColor: hasSelected ? ['#7c6cf0'] : ['#7c6cf0','#2eb87a','#f0a04b','#e84d6b']
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        };

        const setMode = (mode) => {
            if (mode === 'receita') {
                receitaWrap?.classList.remove('d-none');
                const k1 = document.querySelector('#receita-total-pedidos')?.closest('.card-body')?.querySelector('.subheader');
                const k2 = document.querySelector('#receita-total-qtd')?.closest('.card-body')?.querySelector('.subheader');
                const k3 = document.querySelector('#receita-faturamento')?.closest('.card-body')?.querySelector('.subheader');
                const k4 = document.querySelector('#receita-lucro')?.closest('.card-body')?.querySelector('.subheader');
                if (k1) k1.textContent = 'Pedidos da receita';
                if (k2) k2.textContent = 'Qtd total vendida';
                if (k3) k3.textContent = 'Faturamento';
                if (k4) k4.textContent = 'Lucro estimado';
                const t1 = document.querySelector('#receitaDailyChart')?.closest('.card-body')?.querySelector('.card-title');
                const t2 = document.querySelector('#receitaStatusChart')?.closest('.card-body')?.querySelector('.card-title');
                if (t1) t1.textContent = 'Faturamento da receita (30 dias)';
                if (t2) t2.textContent = 'Status para a receita';
                renderReceitaSection(receitaSelect?.value || '0');
            } else {
                receitaWrap?.classList.remove('d-none');
                renderPrecificacaoSection(receitaSelect?.value || '');
            }
        };

        if (window.jQuery && window.jQuery.fn.select2 && receitaSelect) {
            window.jQuery(receitaSelect).select2({
                placeholder: 'Selecione uma receita',
                allowClear: false,
                width: '100%'
            });
        }

        if (receitaSelect) {
            const onReceitaChange = () => {
                if ((analysisModeSelect?.value || 'precificacao') === 'receita') {
                    renderReceitaSection(receitaSelect.value);
                } else {
                    renderPrecificacaoSection(receitaSelect.value);
                }
            };
            receitaSelect.addEventListener('change', onReceitaChange);
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(receitaSelect).on('change.select2', onReceitaChange);
            }
        }
        if (analysisModeSelect) {
            analysisModeSelect.addEventListener('change', () => setMode(analysisModeSelect.value));
        }
        setMode('precificacao');
    </script>
<?= $this->endSection() ?>
