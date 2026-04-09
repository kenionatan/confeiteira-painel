<?php if (empty($pedidos)): ?>
    <tr>
        <td colspan="9" class="text-secondary text-center">Nenhum pedido cadastrado.</td>
    </tr>
<?php else: ?>
    <?php foreach ($pedidos as $pedido): ?>
        <?php
        $status = (string) $pedido['status'];
        $badgeClass = match ($status) {
            'novo' => 'bg-blue-lt text-blue',
            'em_producao' => 'bg-yellow-lt text-yellow',
            'finalizado' => 'bg-cyan-lt text-cyan',
            'entregue' => 'bg-green-lt text-green',
            'cancelado' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
        ?>
        <tr>
            <td><?= esc($pedido['id']) ?></td>
            <td><?= esc($pedido['cliente_nome']) ?></td>
            <td><?= esc($pedido['produto']) ?></td>
            <td>R$ <?= number_format((float) $pedido['valor_total'], 2, ',', '.') ?></td>
            <td><span class="badge <?= esc($badgeClass) ?>"><?= esc(str_replace('_', ' ', $status)) ?></span></td>
            <td><?= !empty($pedido['created_at']) ? esc(date('d/m/Y H:i', strtotime((string) $pedido['created_at']))) : '-' ?></td>
            <td><?= !empty($pedido['data_entrega']) ? esc(date('d/m/Y', strtotime((string) $pedido['data_entrega']))) : '-' ?></td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-icon-soft js-btn-view" data-id="<?= esc($pedido['id']) ?>" title="Visualizar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button type="button" class="btn btn-sm btn-icon-soft js-btn-edit" data-id="<?= esc($pedido['id']) ?>" title="Editar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
