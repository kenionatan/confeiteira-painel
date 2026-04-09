<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Nova categoria</h3>
                <?php if (session()->getFlashdata('errors')): ?>
                    <div class="alert alert-danger">
                        <?php foreach ((array) session()->getFlashdata('errors') as $err): ?>
                            <div><?= esc(is_array($err) ? implode(' ', $err) : $err) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="/categorias-produto/salvar">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" name="nome" value="<?= esc(old('nome')) ?>" required maxlength="80">
                    </div>
                    <button class="btn btn-primary" type="submit">Salvar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($categorias ?? []) as $cat): ?>
                            <tr>
                                <td><?= esc($cat['nome']) ?></td>
                                <td class="text-end">
                                    <a href="/categorias-produto/editar/<?= (int) $cat['id'] ?>" class="btn btn-sm btn-ghost-primary">Editar</a>
                                    <form action="/categorias-produto/excluir/<?= (int) $cat['id'] ?>" method="post" class="d-inline" onsubmit="return confirm('Excluir esta categoria?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-ghost-danger">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
