<?php

namespace App\Controllers;

use App\Models\CategoriaProdutoModel;
use App\Models\ProdutoModel;

class ProdutosController extends BaseController
{
    public function index(): string
    {
        $listData = $this->buildListData(false);

        $catModel = new CategoriaProdutoModel();
        $categorias = $catModel->orderBy('nome', 'ASC')->findAll();

        return view('produtos/index', [
            'title' => 'Produtos',
            'produtos' => $listData['produtos'],
            'pager' => $listData['pager'],
            'filters' => ['q' => $listData['q'], 'categoria_id' => $listData['categoria_id']],
            'categorias' => $categorias,
        ]);
    }

    public function listaAjax()
    {
        if (! $this->request->isAJAX()) {
            return redirect()->to('/produtos');
        }

        $listData = $this->buildListData(true);

        return $this->response->setJSON([
            'tableBodyHtml' => view('produtos/_table_body', [
                'produtos' => $listData['produtos'],
            ]),
            'paginationHtml' => $listData['pager']->links(),
        ]);
    }

    public function novo(): string
    {
        $catModel = new CategoriaProdutoModel();

        return view('produtos/form', [
            'title' => 'Novo produto',
            'produto' => null,
            'categorias' => $catModel->orderBy('nome', 'ASC')->findAll(),
        ]);
    }

    public function salvar()
    {
        if (! $this->validate($this->produtoRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new ProdutoModel();
        $model->insert($this->produtoPayloadFromPost());

        return redirect()->to('/produtos')->with('success', 'Produto cadastrado.');
    }

    public function editar(int $id): string
    {
        $model = new ProdutoModel();
        $produto = $model->find($id);
        if (! $produto) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $catModel = new CategoriaProdutoModel();

        return view('produtos/form', [
            'title' => 'Editar produto',
            'produto' => $produto,
            'categorias' => $catModel->orderBy('nome', 'ASC')->findAll(),
        ]);
    }

    public function atualizar(int $id)
    {
        if (! $this->validate($this->produtoRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new ProdutoModel();
        if (! $model->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model->update($id, $this->produtoPayloadFromPost());

        return redirect()->to('/produtos')->with('success', 'Produto atualizado.');
    }

    public function excluir(int $id)
    {
        $model = new ProdutoModel();
        $model->delete($id);

        return redirect()->to('/produtos')->with('success', 'Produto excluido.');
    }

    private function produtoRules(): array
    {
        return [
            'nome' => 'required|min_length[2]|max_length[150]',
            'categoria_id' => 'permit_empty|is_natural_no_zero',
            'embalagem' => 'permit_empty|max_length[120]',
            'preco' => 'required',
            'qtd_embalagem' => 'required',
            'un_embalagem' => 'required|in_list[g,kg,ml,l,un]',
            'observacoes' => 'permit_empty',
        ];
    }

    private function produtoPayloadFromPost(): array
    {
        $preco = $this->parseDecimal($this->request->getPost('preco'));
        $qtd = $this->parseDecimal($this->request->getPost('qtd_embalagem'));
        $cat = $this->request->getPost('categoria_id');
        $categoriaId = $cat !== null && $cat !== '' ? (int) $cat : null;

        return [
            'categoria_id' => $categoriaId,
            'nome' => trim((string) $this->request->getPost('nome')),
            'embalagem' => trim((string) $this->request->getPost('embalagem')) ?: null,
            'preco' => $preco,
            'qtd_embalagem' => $qtd,
            'un_embalagem' => (string) $this->request->getPost('un_embalagem'),
            'observacoes' => $this->request->getPost('observacoes') ? trim((string) $this->request->getPost('observacoes')) : null,
        ];
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

    private function buildListData(bool $pagerLinksToListaAjax): array
    {
        $model = new ProdutoModel();
        $model->select('produtos.*, categorias_produto.nome as categoria_nome')
            ->join('categorias_produto', 'categorias_produto.id = produtos.categoria_id', 'left');

        $q = trim((string) $this->request->getGet('q'));
        $categoriaId = (int) ($this->request->getGet('categoria_id') ?? 0);

        if ($q !== '') {
            $model->groupStart()
                ->like('produtos.nome', $q)
                ->orLike('produtos.embalagem', $q)
                ->orLike('categorias_produto.nome', $q)
                ->groupEnd();
        }
        if ($categoriaId > 0) {
            $model->where('produtos.categoria_id', $categoriaId);
        }

        $produtos = $model->orderBy('produtos.nome', 'ASC')->paginate(10);
        $pager = $model->pager;
        $pager->setPath($pagerLinksToListaAjax ? 'produtos/lista-ajax' : 'produtos');

        return [
            'produtos' => $produtos,
            'pager' => $pager,
            'q' => $q,
            'categoria_id' => $categoriaId,
        ];
    }
}
