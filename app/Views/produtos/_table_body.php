<?php foreach (($produtos ?? []) as $p): ?>
    <tr>
        <td><?= esc($p['nome']) ?></td>
        <td><?= esc($p['categoria_nome'] ?? '-') ?></td>
        <td><?= esc($p['embalagem'] ?? '-') ?></td>
        <td>R$ <?= number_format((float) ($p['preco'] ?? 0), 2, ',', '.') ?></td>
        <td><?= esc(rtrim(rtrim(number_format((float) ($p['qtd_embalagem'] ?? 0), 4, ',', '.'), '0'), ',')) ?> <?= esc($p['un_embalagem'] ?? '') ?></td>
        <td class="text-end">
            <a href="/produtos/editar/<?= (int) $p['id'] ?>" class="btn btn-sm btn-ghost-primary">Editar</a>
            <form action="/produtos/excluir/<?= (int) $p['id'] ?>" method="post" class="d-inline" onsubmit="return confirm('Excluir este produto?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-ghost-danger">Excluir</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
