<?php

namespace App\Controllers;

use App\Models\PedidoModel;
use App\Models\ClienteModel;
use App\Models\PrecificacaoModel;

class Home extends BaseController
{
    public function index(): string
    {
        $pedidoModel = new PedidoModel();
        $clienteModel = new ClienteModel();
        $precificacaoModel = new PrecificacaoModel();
        $db = \Config\Database::connect();

        $totalPedidos = (int) $pedidoModel->countAllResults();
        $faturamento = (float) ($db->table('pedidos')->selectSum('valor_total')->get()->getRow('valor_total') ?? 0);
        $ticketMedio = $totalPedidos > 0 ? ($faturamento / $totalPedidos) : 0.0;

        $custoEstimado = (float) ($db->table('pedidos p')
            ->select('SUM(p.quantidade * pr.custo) AS custo_estimado', false)
            ->join('precificacoes pr', 'pr.nome_produto = p.produto', 'left')
            ->get()
            ->getRow('custo_estimado') ?? 0);
        $lucroEstimado = $faturamento - $custoEstimado;

        $statusRows = $db->table('pedidos')
            ->select('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $statusLabels = array_column($statusRows, 'status');
        $statusValues = array_map(static fn(array $row): int => (int) $row['total'], $statusRows);

        $dailyRows = $db->query("
            SELECT DATE(created_at) AS dia, SUM(valor_total) AS total
            FROM pedidos
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY dia ASC
        ")->getResultArray();

        $dailyLabels = array_column($dailyRows, 'dia');
        $dailyValues = array_map(static fn(array $row): float => (float) $row['total'], $dailyRows);
        $dailyOrderRows = $db->query("
            SELECT DATE(created_at) AS dia, COUNT(*) AS total
            FROM pedidos
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY dia ASC
        ")->getResultArray();
        $dailyOrderLabels = array_column($dailyOrderRows, 'dia');
        $dailyOrderValues = array_map(static fn(array $row): int => (int) $row['total'], $dailyOrderRows);

        $monthlyRows = $db->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS mes, SUM(valor_total) AS total
            FROM pedidos
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY mes ASC
        ")->getResultArray();
        $monthlyLabels = array_map(
            static fn(array $row): string => date('m/Y', strtotime((string) $row['mes'] . '-01')),
            $monthlyRows
        );
        $monthlyValues = array_map(static fn(array $row): float => (float) $row['total'], $monthlyRows);

        $ordersLast7 = (int) ($db->table('pedidos')->where('created_at >=', date('Y-m-d 00:00:00', strtotime('-6 days')))->countAllResults() ?? 0);
        $revenueLast7 = (float) ($db->table('pedidos')
            ->selectSum('valor_total')
            ->where('created_at >=', date('Y-m-d 00:00:00', strtotime('-6 days')))
            ->get()->getRow('valor_total') ?? 0);
        $pedidosPendentes = (int) ($db->table('pedidos')
            ->whereIn('status', ['novo', 'em_producao'])
            ->countAllResults() ?? 0);

        $topProdutos = $db->table('pedidos')
            ->select('produto, SUM(quantidade) as total_qtd')
            ->groupBy('produto')
            ->orderBy('total_qtd', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $receitas = $precificacaoModel
            ->select('id, nome_produto, custo, preco_sugerido, margem_percentual')
            ->orderBy('nome_produto', 'ASC')
            ->findAll();
        $receitaStats = [];
        foreach ($receitas as $receita) {
            $receitaId = (int) $receita['id'];
            $resumo = $db->table('pedido_itens pi')
                ->select('COUNT(DISTINCT pi.pedido_id) AS total_pedidos, COALESCE(SUM(pi.quantidade), 0) AS total_qtd, COALESCE(SUM(pi.subtotal), 0) AS faturamento', false)
                ->join('pedidos p', 'p.id = pi.pedido_id', 'inner')
                ->where('pi.produto_id', $receitaId)
                ->get()
                ->getRowArray() ?? [];

            $statusReceitaRows = $db->table('pedido_itens pi')
                ->select('p.status, COUNT(DISTINCT pi.pedido_id) AS total', false)
                ->join('pedidos p', 'p.id = pi.pedido_id', 'inner')
                ->where('pi.produto_id', $receitaId)
                ->groupBy('p.status')
                ->get()
                ->getResultArray();

            $dailyReceitaRows = $db->table('pedido_itens pi')
                ->select('DATE(p.created_at) AS dia, COALESCE(SUM(pi.subtotal), 0) AS total', false)
                ->join('pedidos p', 'p.id = pi.pedido_id', 'inner')
                ->where('pi.produto_id', $receitaId)
                ->where('p.created_at >=', date('Y-m-d 00:00:00', strtotime('-30 days')))
                ->groupBy('DATE(p.created_at)')
                ->orderBy('dia', 'ASC')
                ->get()
                ->getResultArray();

            $totalPedidosReceita = (int) ($resumo['total_pedidos'] ?? 0);
            $totalQtdReceita = (int) ($resumo['total_qtd'] ?? 0);
            $faturamentoReceita = (float) ($resumo['faturamento'] ?? 0);
            $custoUnitario = (float) ($receita['custo'] ?? 0);
            $custoTotalReceita = $custoUnitario * $totalQtdReceita;
            $lucroReceita = $faturamentoReceita - $custoTotalReceita;
            $margemReceita = $faturamentoReceita > 0
                ? (($lucroReceita / $faturamentoReceita) * 100)
                : 0.0;

            $receitaStats[(string) $receitaId] = [
                'id' => $receitaId,
                'nome' => (string) ($receita['nome_produto'] ?? ('Receita #' . $receitaId)),
                'totalPedidos' => $totalPedidosReceita,
                'totalQtd' => $totalQtdReceita,
                'faturamento' => $faturamentoReceita,
                'custoTotal' => $custoTotalReceita,
                'lucro' => $lucroReceita,
                'margem' => $margemReceita,
                'statusLabels' => array_column($statusReceitaRows, 'status'),
                'statusValues' => array_map(static fn(array $r): int => (int) $r['total'], $statusReceitaRows),
                'dailyLabels' => array_column($dailyReceitaRows, 'dia'),
                'dailyValues' => array_map(static fn(array $r): float => (float) $r['total'], $dailyReceitaRows),
            ];
        }
        $receitaInicialId = ! empty($receitas) ? (int) $receitas[0]['id'] : 0;

        $precResumo = $db->table('precificacoes')
            ->select('COUNT(*) AS total_receitas, COALESCE(AVG(custo), 0) AS custo_medio, COALESCE(AVG(preco_sugerido), 0) AS preco_medio, COALESCE(AVG(margem_percentual), 0) AS margem_media', false)
            ->get()
            ->getRowArray() ?? [];
        $precTopRows = $db->table('precificacoes')
            ->select('nome_produto, custo, preco_sugerido')
            ->orderBy('preco_sugerido', 'DESC')
            ->limit(8)
            ->get()
            ->getResultArray();
        $precFaixaRows = $db->table('precificacoes')
            ->select("
                CASE
                    WHEN margem_percentual < 20 THEN '<20%'
                    WHEN margem_percentual < 40 THEN '20-39%'
                    WHEN margem_percentual < 60 THEN '40-59%'
                    ELSE '60%+'
                END AS faixa,
                COUNT(*) AS total
            ", false)
            ->groupBy('faixa')
            ->get()
            ->getResultArray();

        $precStats = [
            'totalReceitas' => (int) ($precResumo['total_receitas'] ?? 0),
            'custoMedio' => (float) ($precResumo['custo_medio'] ?? 0),
            'precoMedio' => (float) ($precResumo['preco_medio'] ?? 0),
            'margemMedia' => (float) ($precResumo['margem_media'] ?? 0),
            'topLabels' => array_map(static fn(array $r): string => (string) $r['nome_produto'], $precTopRows),
            'topCustoValues' => array_map(static fn(array $r): float => (float) $r['custo'], $precTopRows),
            'topPrecoValues' => array_map(static fn(array $r): float => (float) $r['preco_sugerido'], $precTopRows),
            'faixaLabels' => array_map(static fn(array $r): string => (string) $r['faixa'], $precFaixaRows),
            'faixaValues' => array_map(static fn(array $r): int => (int) $r['total'], $precFaixaRows),
        ];
        $precReceitasMap = [];
        foreach ($receitas as $receita) {
            $rid = (int) $receita['id'];
            $custo = (float) ($receita['custo'] ?? 0);
            $preco = (float) ($receita['preco_sugerido'] ?? 0);
            $margem = (float) ($receita['margem_percentual'] ?? 0);
            $faixa = '<20%';
            if ($margem >= 60) {
                $faixa = '60%+';
            } elseif ($margem >= 40) {
                $faixa = '40-59%';
            } elseif ($margem >= 20) {
                $faixa = '20-39%';
            }
            $precReceitasMap[(string) $rid] = [
                'id' => $rid,
                'nome' => (string) ($receita['nome_produto'] ?? ('Receita #' . $rid)),
                'custo' => $custo,
                'preco' => $preco,
                'margem' => $margem,
                'ganho' => $preco - $custo,
                'faixa' => $faixa,
            ];
        }

        if (empty($statusLabels)) {
            $statusLabels = ['Sem dados'];
            $statusValues = [1];
        }
        if (empty($dailyLabels)) {
            $dailyLabels = [date('Y-m-d')];
            $dailyValues = [0];
        }
        if (empty($dailyOrderLabels)) {
            $dailyOrderLabels = [date('Y-m-d')];
            $dailyOrderValues = [0];
        }
        if (empty($monthlyLabels)) {
            $monthlyLabels = [date('m/Y')];
            $monthlyValues = [0];
        }

        return view('dashboard', [
            'title' => 'Dashboard',
            'totalPedidos' => $totalPedidos,
            'totalClientes' => (int) $clienteModel->countAllResults(),
            'totalReceitas' => (int) $precificacaoModel->countAllResults(),
            'faturamento' => $faturamento,
            'ticketMedio' => $ticketMedio,
            'ordersLast7' => $ordersLast7,
            'revenueLast7' => $revenueLast7,
            'pedidosPendentes' => $pedidosPendentes,
            'custoEstimado' => $custoEstimado,
            'lucroEstimado' => $lucroEstimado,
            'statusLabels' => $statusLabels,
            'statusValues' => $statusValues,
            'dailyLabels' => $dailyLabels,
            'dailyValues' => $dailyValues,
            'dailyOrderLabels' => $dailyOrderLabels,
            'dailyOrderValues' => $dailyOrderValues,
            'monthlyLabels' => $monthlyLabels,
            'monthlyValues' => $monthlyValues,
            'topProdutos' => $topProdutos,
            'receitasOptions' => $receitas,
            'receitaStats' => $receitaStats,
            'receitaInicialId' => $receitaInicialId,
            'precStats' => $precStats,
            'precReceitasMap' => $precReceitasMap,
        ]);
    }
}
