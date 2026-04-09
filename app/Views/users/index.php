<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="m-0">Usuarios cadastrados</h3>
    <a href="/usuarios/novo" class="btn btn-primary">Novo usuario</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Grupos</th>
                    <th>Tema</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= esc($user['id']) ?></td>
                        <td><?= esc($user['name']) ?></td>
                        <td><?= esc($user['email']) ?></td>
                        <td><?= esc($user['groups_names'] ?: '-') ?></td>
                        <td><?= esc($user['preferred_theme']) ?></td>
                        <td><?= (int) $user['is_active'] ? 'Ativo' : 'Inativo' ?></td>
                        <td>
                            <a href="/usuarios/editar/<?= esc($user['id']) ?>" class="btn btn-sm btn-icon-soft" title="Editar usuario">
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
