<?php

namespace App\Controllers;

use App\Models\PrecificacaoModel;
use App\Models\ProdutoModel;

class PrecificacaoController extends BaseController
{
    public function index(): string
    {
        $listData = $this->buildListData();
        $model = new PrecificacaoModel();
        $receitas = $model->select('id, nome_produto')->orderBy('nome_produto', 'ASC')->findAll();

        $produtoModel = new ProdutoModel();
        $produtosCatalogo = $produtoModel->select('produtos.id, produtos.nome, produtos.embalagem, produtos.preco, produtos.qtd_embalagem, produtos.un_embalagem, categorias_produto.nome as categoria_nome')
            ->join('categorias_produto', 'categorias_produto.id = produtos.categoria_id', 'left')
            ->orderBy('produtos.nome', 'ASC')
            ->findAll();

        return view('precificacao/index', [
            'title' => 'Precificacao',
            'itens' => $listData['itens'],
            'pager' => $listData['pager'],
            'filters' => [
                'q' => $listData['q'],
                'receita_id' => $listData['receita_id'],
            ],
            'receitas' => $receitas,
            'produtos_catalogo' => $produtosCatalogo,
        ]);
    }

    public function listaAjax()
    {
        if (! $this->request->isAJAX()) {
            return redirect()->to('/precificacao');
        }

        $listData = $this->buildListData();
        return $this->response->setJSON([
            'itensMap' => array_column($listData['itens'], null, 'id'),
            'tableBodyHtml' => view('precificacao/_table_body', [
                'itens' => $listData['itens'],
            ]),
            'paginationHtml' => $listData['pager']->links(),
        ]);
    }

    public function novo(): string
    {
        return view('precificacao/novo', [
            'title' => 'Novo item de precificacao',
        ]);
    }

    public function salvar()
    {
        $rules = [
            'nome_produto' => 'required|min_length[3]',
            'margem_percentual' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = $this->buildRecipePayloadFromRequest();
        if (! $payload['ok']) {
            return redirect()->back()->withInput()->with('errors', $payload['errors']);
        }

        $model = new PrecificacaoModel();
        $model->insert([
            'nome_produto' => $this->request->getPost('nome_produto'),
            'categoria' => 'Receita',
            'custo' => $payload['custo'],
            'margem_percentual' => $payload['margem'],
            'preco_sugerido' => $payload['preco_sugerido'],
            'observacoes' => json_encode($payload['observacoes'], JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()->to('/precificacao')->with('success', 'Item de precificacao cadastrado com sucesso.');
    }

    public function editar(int $id): string
    {
        return redirect()->to('/precificacao');
    }

    public function atualizar(int $id)
    {
        $rules = [
            'nome_produto' => 'required|min_length[3]',
            'margem_percentual' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = $this->buildRecipePayloadFromRequest();
        if (! $payload['ok']) {
            return redirect()->back()->withInput()->with('errors', $payload['errors']);
        }

        $model = new PrecificacaoModel();
        $model->update($id, [
            'nome_produto' => $this->request->getPost('nome_produto'),
            'categoria' => 'Receita',
            'custo' => $payload['custo'],
            'margem_percentual' => $payload['margem'],
            'preco_sugerido' => $payload['preco_sugerido'],
            'observacoes' => json_encode($payload['observacoes'], JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()->to('/precificacao')->with('success', 'Item atualizado com sucesso.');
    }

    public function excluir(int $id)
    {
        $model = new PrecificacaoModel();
        $model->delete($id);
        return redirect()->to('/precificacao')->with('success', 'Item excluido com sucesso.');
    }

    private function parseDecimal(mixed $value): float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        if (str_contains($raw, ',')) {
            $normalized = str_replace('.', '', $raw);
            $normalized = str_replace(',', '.', $normalized);
            return is_numeric($normalized) ? (float) $normalized : 0.0;
        }

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private function normalizeUnit(float $value, string $unit): array
    {
        $u = strtolower($unit);
        if ($u === 'kg') {
            return ['kind' => 'mass', 'amount' => $value * 1000];
        }
        if ($u === 'g') {
            return ['kind' => 'mass', 'amount' => $value];
        }
        if ($u === 'l') {
            return ['kind' => 'vol', 'amount' => $value * 1000];
        }
        if ($u === 'ml') {
            return ['kind' => 'vol', 'amount' => $value];
        }
        if ($u === 'un') {
            return ['kind' => 'count', 'amount' => $value];
        }
        return ['kind' => 'invalid', 'amount' => 0];
    }

    private function calcularSubtotal(float $preco, float $qtdEmb, string $unEmb, float $qtdRec, string $unRec): float
    {
        $p = $this->normalizeUnit($qtdEmb, $unEmb);
        $r = $this->normalizeUnit($qtdRec, $unRec);
        if ($p['kind'] === 'invalid' || $r['kind'] === 'invalid' || $p['kind'] !== $r['kind'] || (float) $p['amount'] <= 0) {
            return 0.0;
        }
        return ($preco / (float) $p['amount']) * (float) $r['amount'];
    }

    private function buildRecipePayloadFromRequest(): array
    {
        $ingredientes = (array) $this->request->getPost('ingredientes');
        $ingredientesSanitizados = [];
        $totalIngredientes = 0.0;

        foreach ($ingredientes as $ing) {
            $nome = trim((string) ($ing['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }

            $preco = $this->parseDecimal($ing['preco'] ?? 0);
            $qtdEmbalagem = $this->parseDecimal($ing['qtd_embalagem'] ?? 0);
            $qtdReceita = $this->parseDecimal($ing['qtd_receita'] ?? 0);
            $unEmb = trim((string) ($ing['un_embalagem'] ?? 'g'));
            $unRec = trim((string) ($ing['un_receita'] ?? 'g'));
            $subtotal = $this->calcularSubtotal($preco, $qtdEmbalagem, $unEmb, $qtdReceita, $unRec);
            $totalIngredientes += $subtotal;

            $produtoId = isset($ing['produto_id']) ? (int) $ing['produto_id'] : 0;

            $ingredientesSanitizados[] = [
                'produto_id' => $produtoId > 0 ? $produtoId : null,
                'nome' => $nome,
                'embalagem' => trim((string) ($ing['embalagem'] ?? '')),
                'preco' => (float) $preco,
                'qtd_embalagem' => (float) $qtdEmbalagem,
                'un_embalagem' => $unEmb,
                'qtd_receita' => (float) $qtdReceita,
                'un_receita' => $unRec,
                'subtotal' => (float) $subtotal,
            ];
        }

        if (empty($ingredientesSanitizados)) {
            return ['ok' => false, 'errors' => ['Adicione pelo menos um ingrediente valido.']];
        }

        // Mao de obra opcional: em branco = 0 (sempre persistir numeros no JSON)
        $horas = $this->parseDecimal($this->request->getPost('horas_trabalhadas') ?? '');
        $valorHora = $this->parseDecimal($this->request->getPost('valor_hora') ?? '');
        $custoMaoObra = $horas * $valorHora;
        $custo = $totalIngredientes + $custoMaoObra;
        $margemRaw = $this->request->getPost('margem_percentual');
        $margemStr = trim((string) ($margemRaw ?? ''));
        $margem = $margemStr === '' ? 30.0 : $this->parseDecimal($margemRaw);
        $precoFinal = $custo + ($custo * ($margem / 100));
        $lucroEstimado = $precoFinal - $custo;

        return [
            'ok' => true,
            'custo' => $custo,
            'margem' => $margem,
            'preco_sugerido' => $precoFinal,
            'observacoes' => [
                'ingredientes' => $ingredientesSanitizados,
                'horas_trabalhadas' => (float) $horas,
                'valor_hora' => (float) $valorHora,
                'custo_ingredientes' => (float) $totalIngredientes,
                'custo_mao_obra' => (float) $custoMaoObra,
                'margem_percentual' => (float) $margem,
                'custo_total_receita' => (float) $custo,
                'preco_venda_sugerido' => (float) $precoFinal,
                'lucro_estimado' => (float) $lucroEstimado,
            ],
        ];
    }

    private function buildListData(): array
    {
        $model = new PrecificacaoModel();
        $q = trim((string) $this->request->getGet('q'));
        $receitaId = (int) ($this->request->getGet('receita_id') ?? 0);
        if ($q !== '') {
            $model->groupStart()
                ->like('nome_produto', $q)
                ->orLike('categoria', $q)
                ->groupEnd();
        }
        if ($receitaId > 0) {
            $model->where('id', $receitaId);
        }

        return [
            'itens' => $model->orderBy('id', 'DESC')->paginate(10),
            'pager' => $model->pager,
            'q' => $q,
            'receita_id' => $receitaId,
        ];
    }
}
