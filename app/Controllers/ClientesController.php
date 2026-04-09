<?php

namespace App\Controllers;

use App\Models\ClienteModel;

class ClientesController extends BaseController
{
    public function index(): string
    {
        $listData = $this->buildListData(false);

        return view('clientes/index', [
            'title' => 'Clientes',
            'clientes' => $listData['clientes'],
            'pager' => $listData['pager'],
            'filters' => ['q' => $listData['q']],
        ]);
    }

    public function listaAjax()
    {
        if (! $this->request->isAJAX()) {
            return redirect()->to('/painel/clientes');
        }

        $listData = $this->buildListData(true);
        return $this->response->setJSON([
            'tableBodyHtml' => view('clientes/_table_body', [
                'clientes' => $listData['clientes'],
            ]),
            'paginationHtml' => $listData['pager']->links(),
        ]);
    }

    public function salvar()
    {
        $rules = [
            'nome' => 'required|min_length[3]',
            'telefone' => 'permit_empty|max_length[30]',
            'endereco' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new ClienteModel();
        $model->insert([
            'nome' => $this->request->getPost('nome'),
            'telefone' => $this->request->getPost('telefone'),
            'endereco' => $this->request->getPost('endereco'),
            'observacoes' => $this->request->getPost('observacoes'),
        ]);

        return redirect()->to('/painel/clientes')->with('success', 'Cliente cadastrado com sucesso.');
    }

    private function buildListData(bool $pagerLinksToAjax): array
    {
        $model = new ClienteModel();
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $model->groupStart()
                ->like('nome', $q)
                ->orLike('telefone', $q)
                ->orLike('endereco', $q)
                ->groupEnd();
        }

        $clientes = $model->orderBy('nome', 'ASC')->paginate(10);
        $pager = $model->pager;
        $pager->setPath($pagerLinksToAjax ? 'painel/clientes/lista-ajax' : 'painel/clientes');

        return [
            'clientes' => $clientes,
            'pager' => $pager,
            'q' => $q,
        ];
    }
}
