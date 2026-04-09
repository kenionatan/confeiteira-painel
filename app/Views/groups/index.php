<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="m-0">Grupos</h3>
    <a href="/grupos/novo" class="btn btn-primary">Novo grupo</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Descricao</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td><?= esc($group['id']) ?></td>
                        <td><?= esc($group['name']) ?></td>
                        <td><?= esc($group['description'] ?: '-') ?></td>
                        <td>
                            <a href="/grupos/editar/<?= esc($group['id']) ?>" class="btn btn-sm btn-icon-soft" title="Editar grupo">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
