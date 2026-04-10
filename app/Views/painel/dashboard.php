<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="subheader text-secondary">Clientes</div>
                <div class="h1 mb-0"><?= esc((string) ($totalClientes ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="subheader text-secondary">Assinaturas ativas</div>
                <div class="h1 mb-0"><?= esc((string) ($subscriptionsActive ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="subheader text-secondary">Em trial</div>
                <div class="h1 mb-0"><?= esc((string) ($subscriptionsTrial ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="subheader text-secondary">Planos cadastrados</div>
                <div class="h1 mb-0"><?= esc((string) ($totalPlans ?? 0)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Últimos cadastros</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Domínio</th>
                    <th>Email</th>
                    <th>Cadastro</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentClientes)): ?>
                    <tr>
                        <td colspan="4" class="text-secondary text-center">Nenhum cliente ainda.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentClientes as $c): ?>
                        <tr>
                            <td><?= esc($c['nome'] ?? '') ?></td>
                            <td><?= esc($c['dominio'] ?? '') ?></td>
                            <td><?= esc($c['email'] ?? '') ?></td>
                            <td class="text-secondary"><?= esc($c['created_at'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-end">
        <a href="/painel/clientes" class="btn btn-primary">Ver todos os clientes</a>
    </div>
</div>
<?= $this->endSection() ?>
