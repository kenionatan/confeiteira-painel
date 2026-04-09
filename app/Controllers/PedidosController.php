<?php

namespace App\Controllers;

use App\Models\ClienteModel;
use App\Models\PedidoItemModel;
use App\Models\PedidoModel;
use App\Models\PrecificacaoModel;

class PedidosController extends BaseController
{
    public function index(): string
    {
        $clienteModel = new ClienteModel();
        $precificacaoModel = new PrecificacaoModel();
        $listData = $this->buildListData();

        return view('pedidos/index', [
            'title' => 'Pedidos',
            'pedidos' => $listData['pedidos'],
            'itensPorPedido' => $listData['itensPorPedido'],
            'clientes' => $clienteModel->orderBy('nome', 'ASC')->findAll(),
            'produtos' => $precificacaoModel->orderBy('nome_produto', 'ASC')->findAll(),
            'pager' => $listData['pager'],
            'filters' => [
                'q' => $listData['q'],
                'status' => $listData['status'],
                'data' => $listData['data'],
            ],
        ]);
    }

    public function listaAjax()
    {
        if (! $this->request->isAJAX()) {
            return redirect()->to('/pedidos');
        }

        $listData = $this->buildListData();
        return $this->response->setJSON([
            'pedidos' => $listData['pedidos'],
            'itensPorPedido' => $listData['itensPorPedido'],
            'tableBodyHtml' => view('pedidos/_table_body', [
                'pedidos' => $listData['pedidos'],
            ]),
            'paginationHtml' => $listData['pager']->links(),
        ]);
    }

    public function novo(): string
    {
        $clienteModel = new ClienteModel();
        $precificacaoModel = new PrecificacaoModel();

        return view('pedidos/novo', [
            'title' => 'Novo pedido',
            'clientes' => $clienteModel->orderBy('nome', 'ASC')->findAll(),
            'produtos' => $precificacaoModel->orderBy('nome_produto', 'ASC')->findAll(),
        ]);
    }

    public function salvar()
    {
        $rules = [
            'cliente_id' => 'permit_empty|integer',
            'cliente_nome' => 'permit_empty|min_length[3]',
            'status' => 'required|in_list[novo,em_producao,finalizado,entregue,cancelado]',
            'data_entrega' => 'permit_empty|valid_date[Y-m-d]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $precificacaoModel = new PrecificacaoModel();
        $linhas = (array) $this->request->getPost('linhas');
        $linhasPedido = [];
        $valorTotal = 0.0;
        $quantidadeTotal = 0;
        $nomesProdutos = [];

        foreach ($linhas as $linha) {
            $produtoId = (int) ($linha['produto_id'] ?? 0);
            $quantidade = (int) ($linha['quantidade'] ?? 0);
            if ($produtoId <= 0 || $quantidade <= 0) {
                continue;
            }

            $produto = $precificacaoModel->find($produtoId);
            if (! $produto) {
                continue;
            }

            $valorUnitario = (float) $produto['preco_sugerido'];
            $subtotal = $valorUnitario * $quantidade;
            $valorTotal += $subtotal;
            $quantidadeTotal += $quantidade;
            $nomesProdutos[] = (string) $produto['nome_produto'];
            $linhasPedido[] = [
                'produto_id' => $produtoId,
                'produto_nome' => (string) $produto['nome_produto'],
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'subtotal' => $subtotal,
            ];
        }

        if (empty($linhasPedido)) {
            return redirect()->back()->withInput()->with('errors', ['Adicione pelo menos um item no pedido.']);
        }
        $clienteNome = trim((string) $this->request->getPost('cliente_nome'));
        $clienteId = (int) ($this->request->getPost('cliente_id') ?? 0);
        if ($clienteId > 0) {
            $clienteModel = new ClienteModel();
            $cliente = $clienteModel->find($clienteId);
            if ($cliente) {
                $clienteNome = (string) $cliente['nome'];
            }
        }

        if ($clienteNome === '') {
            return redirect()->back()->withInput()->with('errors', ['Selecione ou informe um cliente.']);
        }

        $produtoResumo = $nomesProdutos[0] ?? 'Pedido';
        if (count($nomesProdutos) > 1) {
            $produtoResumo .= ' (+' . (count($nomesProdutos) - 1) . ')';
        }
        $valorUnitarioMedio = $quantidadeTotal > 0 ? ($valorTotal / $quantidadeTotal) : 0;
        $payloadObservacao = [
            'nota' => (string) $this->request->getPost('observacoes'),
            'linhas' => $linhasPedido,
        ];

        $db = \Config\Database::connect();
        $model = new PedidoModel();
        $itemModel = new PedidoItemModel();

        $db->transStart();
        $pedidoId = $model->insert([
            'cliente_nome' => $clienteNome,
            'produto' => $produtoResumo,
            'quantidade' => $quantidadeTotal,
            'valor_unitario' => $valorUnitarioMedio,
            'valor_total' => $valorTotal,
            'status' => $this->request->getPost('status'),
            'data_entrega' => $this->request->getPost('data_entrega') ?: null,
            'observacoes' => json_encode($payloadObservacao, JSON_UNESCAPED_UNICODE),
        ], true);

        foreach ($linhasPedido as $linha) {
            $linha['pedido_id'] = $pedidoId;
            $itemModel->insert($linha);
        }
        $db->transComplete();

        return redirect()->to('/pedidos')->with('success', 'Pedido cadastrado com sucesso.');
    }

    public function ver(int $id): string
    {
        $pedido = $this->getPedidoOr404($id);
        return view('pedidos/ver', [
            'title' => 'Visualizar pedido',
            'pedido' => $pedido,
            'itens' => $this->getItensPedido($id, $pedido),
        ]);
    }

    public function editar(int $id): string
    {
        $pedido = $this->getPedidoOr404($id);
        $clienteModel = new ClienteModel();
        $precificacaoModel = new PrecificacaoModel();
        return view('pedidos/editar', [
            'title' => 'Editar pedido',
            'pedido' => $pedido,
            'itens' => $this->getItensPedido($id, $pedido),
            'clientes' => $clienteModel->orderBy('nome', 'ASC')->findAll(),
            'produtos' => $precificacaoModel->orderBy('nome_produto', 'ASC')->findAll(),
        ]);
    }

    public function atualizar(int $id)
    {
        $pedido = $this->getPedidoOr404($id);
        $rules = [
            'cliente_id' => 'permit_empty|integer',
            'cliente_nome' => 'permit_empty|min_length[3]',
            'status' => 'required|in_list[novo,em_producao,finalizado,entregue,cancelado]',
            'data_entrega' => 'permit_empty|valid_date[Y-m-d]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $precificacaoModel = new PrecificacaoModel();
        $linhas = (array) $this->request->getPost('linhas');
        $linhasPedido = [];
        $valorTotal = 0.0;
        $quantidadeTotal = 0;
        $nomesProdutos = [];
        foreach ($linhas as $linha) {
            $produtoId = (int) ($linha['produto_id'] ?? 0);
            $quantidade = (int) ($linha['quantidade'] ?? 0);
            if ($produtoId <= 0 || $quantidade <= 0) {
                continue;
            }
            $produto = $precificacaoModel->find($produtoId);
            if (! $produto) {
                continue;
            }
            $valorUnitario = (float) $produto['preco_sugerido'];
            $subtotal = $valorUnitario * $quantidade;
            $valorTotal += $subtotal;
            $quantidadeTotal += $quantidade;
            $nomesProdutos[] = (string) $produto['nome_produto'];
            $linhasPedido[] = [
                'produto_id' => $produtoId,
                'produto_nome' => (string) $produto['nome_produto'],
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'subtotal' => $subtotal,
            ];
        }
        if (empty($linhasPedido)) {
            return redirect()->back()->withInput()->with('errors', ['Adicione pelo menos um item no pedido.']);
        }

        $clienteNome = trim((string) $this->request->getPost('cliente_nome'));
        $clienteId = (int) ($this->request->getPost('cliente_id') ?? 0);
        if ($clienteId > 0) {
            $clienteModel = new ClienteModel();
            $cliente = $clienteModel->find($clienteId);
            if ($cliente) {
                $clienteNome = (string) $cliente['nome'];
            }
        }
        if ($clienteNome === '') {
            return redirect()->back()->withInput()->with('errors', ['Selecione ou informe um cliente.']);
        }

        $produtoResumo = $nomesProdutos[0] ?? 'Pedido';
        if (count($nomesProdutos) > 1) {
            $produtoResumo .= ' (+' . (count($nomesProdutos) - 1) . ')';
        }
        $valorUnitarioMedio = $quantidadeTotal > 0 ? ($valorTotal / $quantidadeTotal) : 0;
        $payloadObservacao = [
            'nota' => (string) $this->request->getPost('observacoes'),
            'linhas' => $linhasPedido,
        ];

        $db = \Config\Database::connect();
        $model = new PedidoModel();
        $itemModel = new PedidoItemModel();
        $db->transStart();
        $model->update($id, [
            'cliente_nome' => $clienteNome,
            'produto' => $produtoResumo,
            'quantidade' => $quantidadeTotal,
            'valor_unitario' => $valorUnitarioMedio,
            'valor_total' => $valorTotal,
            'status' => $this->request->getPost('status'),
            'data_entrega' => $this->request->getPost('data_entrega') ?: null,
            'observacoes' => json_encode($payloadObservacao, JSON_UNESCAPED_UNICODE),
        ]);
        $itemModel->where('pedido_id', $id)->delete();
        foreach ($linhasPedido as $linha) {
            $linha['pedido_id'] = $id;
            $itemModel->insert($linha);
        }
        $db->transComplete();

        return redirect()->to('/pedidos/ver/' . $id)->with('success', 'Pedido atualizado com sucesso.');
    }

    private function getPedidoOr404(int $id): array
    {
        $model = new PedidoModel();
        $pedido = $model->find($id);
        if (! $pedido) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $pedido;
    }

    private function getItensPedido(int $pedidoId, array $pedido): array
    {
        $itemModel = new PedidoItemModel();
        $itens = $itemModel->where('pedido_id', $pedidoId)->findAll();
        if (! empty($itens)) {
            return $itens;
        }

        $obs = [];
        if (! empty($pedido['observacoes'])) {
            $decoded = json_decode((string) $pedido['observacoes'], true);
            $obs = is_array($decoded) ? $decoded : [];
        }
        if (! empty($obs['linhas']) && is_array($obs['linhas'])) {
            return $obs['linhas'];
        }
        return [];
    }

    private function buildListData(): array
    {
        $model = new PedidoModel();
        $itemModel = new PedidoItemModel();
        $q = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $data = trim((string) $this->request->getGet('data'));

        if ($q !== '') {
            $model->groupStart()
                ->like('cliente_nome', $q)
                ->orLike('produto', $q)
                ->groupEnd();
        }
        if ($status !== '') {
            $model->where('status', $status);
        }
        if ($data !== '') {
            $model->where('DATE(created_at)', $data);
        }

        $pedidos = $model->orderBy('id', 'DESC')->paginate(10);
        $pedidoIds = array_map(static fn(array $p): int => (int) $p['id'], $pedidos);
        $itensPorPedido = [];
        if (! empty($pedidoIds)) {
            $itens = $itemModel->whereIn('pedido_id', $pedidoIds)->orderBy('id', 'ASC')->findAll();
            foreach ($itens as $item) {
                $pid = (int) $item['pedido_id'];
                if (! isset($itensPorPedido[$pid])) {
                    $itensPorPedido[$pid] = [];
                }
                $itensPorPedido[$pid][] = $item;
            }
        }

        return [
            'pedidos' => $pedidos,
            'itensPorPedido' => $itensPorPedido,
            'pager' => $model->pager,
            'q' => $q,
            'status' => $status,
            'data' => $data,
        ];
    }
}
