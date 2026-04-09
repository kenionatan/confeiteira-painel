<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between mb-3">
    <h3 class="m-0">Pedido #<?= esc($pedido['id']) ?></h3>
    <div class="d-flex gap-2">
        <a href="/pedidos/editar/<?= esc($pedido['id']) ?>" class="btn btn-primary">Editar pedido</a>
        <a href="/pedidos" class="btn btn-secondary">Voltar</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h4 class="card-title">Cabecalho do pedido</h4>
        <div class="row">
            <div class="col-md-4"><strong>Cliente:</strong> <?= esc($pedido['cliente_nome']) ?></div>
            <div class="col-md-2"><strong>Status:</strong> <?= esc(str_replace('_', ' ', (string) $pedido['status'])) ?></div>
            <div class="col-md-3"><strong>Criado em:</strong> <?= !empty($pedido['created_at']) ? esc(date('d/m/Y H:i', strtotime((string) $pedido['created_at']))) : '-' ?></div>
            <div class="col-md-3"><strong>Entrega:</strong> <?= !empty($pedido['data_entrega']) ? esc(date('d/m/Y', strtotime((string) $pedido['data_entrega']))) : '-' ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h4 class="card-title">Itens do pedido</h4>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Valor unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($itens ?? []) as $item): ?>
                        <tr>
                            <td><?= esc($item['produto_nome'] ?? '-') ?></td>
                            <td><?= esc($item['quantidade'] ?? 0) ?></td>
                            <td>R$ <?= number_format((float) ($item['valor_unitario'] ?? 0), 2, ',', '.') ?></td>
                            <td>R$ <?= number_format((float) ($item['subtotal'] ?? 0), 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Total</th>
                        <th>R$ <?= number_format((float) $pedido['valor_total'], 2, ',', '.') ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
