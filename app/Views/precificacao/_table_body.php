<?php if (empty($itens)): ?>
    <tr>
        <td colspan="6" class="text-secondary text-center">Nenhum item cadastrado.</td>
    </tr>
<?php else: ?>
    <?php foreach ($itens as $item): ?>
        <tr>
            <td><?= esc($item['nome_produto']) ?></td>
            <td><?= esc($item['categoria'] ?? '-') ?></td>
            <td>R$ <?= number_format((float) $item['custo'], 2, ',', '.') ?></td>
            <td><?= number_format((float) $item['margem_percentual'], 2, ',', '.') ?></td>
            <td>R$ <?= number_format((float) $item['preco_sugerido'], 2, ',', '.') ?></td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-icon-soft me-1 js-edit-item" data-item-id="<?= esc($item['id']) ?>" title="Editar">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-edit" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
                </button>
                <form method="post" action="/precificacao/excluir/<?= esc($item['id']) ?>" class="d-inline" onsubmit="return confirm('Excluir item de precificacao?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-icon-soft-danger" title="Excluir">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg>
                    </button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
