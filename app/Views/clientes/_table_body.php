<?php if (empty($clientes)): ?>
    <tr><td colspan="3" class="text-secondary text-center">Nenhum cliente cadastrado.</td></tr>
<?php else: ?>
    <?php foreach ($clientes as $cliente): ?>
        <tr>
            <td><?= esc($cliente['nome']) ?></td>
            <td><?= esc($cliente['telefone'] ?: '-') ?></td>
            <td><?= esc($cliente['endereco'] ?: '-') ?></td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
