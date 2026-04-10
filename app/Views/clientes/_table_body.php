<?php if (empty($clientes)): ?>
    <tr><td colspan="6" class="text-secondary text-center">Nenhum cliente encontrado.</td></tr>
<?php else: ?>
    <?php foreach ($clientes as $cliente): ?>
        <tr class="cliente-row cursor-pointer" role="button" tabindex="0" data-cliente-id="<?= (int) ($cliente['id'] ?? 0) ?>" title="Ver detalhes">
            <td><?= esc($cliente['nome'] ?? '') ?></td>
            <td><?= esc($cliente['dominio'] ?? '') ?></td>
            <td><?= esc($cliente['email'] ?? '') ?></td>
            <td><?= esc($cliente['whatsapp'] ?? '') ?></td>
            <td>
                <?php
                $last4 = $cliente['cartao_ultimos4'] ?? '';
                $brand = $cliente['cartao_bandeira'] ?? '';
                if ($last4 !== '' && $last4 !== '0000') {
                    echo esc(($brand !== '' ? strtoupper($brand) . ' ' : '') . '•••• ' . $last4);
                } else {
                    echo '<span class="text-secondary">—</span>';
                }
                ?>
            </td>
            <td class="text-secondary">
                <?= esc($cliente['created_at'] ?? '—') ?>
                <?php
                $tenantStatus = (string) ($cliente['tenant_status'] ?? '');
                if ($tenantStatus !== '') {
                    $statusLabel = match ($tenantStatus) {
                        'ready' => 'Ambiente pronto',
                        'failed' => 'Provisionamento falhou',
                        'provisioning' => 'Provisionando',
                        default => 'Pendente',
                    };
                    $statusClass = match ($tenantStatus) {
                        'ready' => 'success',
                        'failed' => 'danger',
                        'provisioning' => 'warning',
                        default => 'secondary',
                    };
                    echo '<div><span class="badge bg-' . esc($statusClass) . '-lt mt-1">' . esc($statusLabel) . '</span></div>';
                }
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
