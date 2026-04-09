<?php

namespace App\Controllers;

use App\Models\CategoriaProdutoModel;

class CategoriasProdutoController extends BaseController
{
    public function index(): string
    {
        $model = new CategoriaProdutoModel();
        $categorias = $model->orderBy('nome', 'ASC')->findAll();

        return view('categorias_produto/index', [
            'title' => 'Categorias de produto',
            'categorias' => $categorias,
        ]);
    }

    public function salvar()
    {
        $rules = ['nome' => 'required|min_length[2]|max_length[80]'];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new CategoriaProdutoModel();
        $model->insert(['nome' => trim((string) $this->request->getPost('nome'))]);

        return redirect()->to('/categorias-produto')->with('success', 'Categoria cadastrada.');
    }

    public function editar(int $id): string
    {
        $model = new CategoriaProdutoModel();
        $categoria = $model->find($id);
        if (! $categoria) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('categorias_produto/form', [
            'title' => 'Editar categoria',
            'categoria' => $categoria,
        ]);
    }

    public function atualizar(int $id)
    {
        $rules = ['nome' => 'required|min_length[2]|max_length[80]'];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new CategoriaProdutoModel();
        if (! $model->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model->update($id, ['nome' => trim((string) $this->request->getPost('nome'))]);

        return redirect()->to('/categorias-produto')->with('success', 'Categoria atualizada.');
    }

    public function excluir(int $id)
    {
        $model = new CategoriaProdutoModel();
        $model->delete($id);

        return redirect()->to('/categorias-produto')->with('success', 'Categoria excluida.');
    }
}
