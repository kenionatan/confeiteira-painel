<?php

namespace App\Controllers;

use App\Models\GroupModel;

class GroupsController extends BaseController
{
    public function index(): string
    {
        $model = new GroupModel();
        return view('groups/index', [
            'title' => 'Grupos',
            'groups' => $model->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function novo(): string
    {
        return view('groups/form', [
            'title' => 'Novo grupo',
            'group' => null,
        ]);
    }

    public function salvar()
    {
        $rules = [
            'name' => 'required|min_length[3]|is_unique[groups.name]',
            'description' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new GroupModel();
        $model->insert([
            'name' => strtolower((string) $this->request->getPost('name')),
            'description' => $this->request->getPost('description'),
        ]);

        return redirect()->to('/grupos')->with('success', 'Grupo cadastrado com sucesso.');
    }

    public function editar(int $id): string
    {
        $model = new GroupModel();
        $group = $model->find($id);
        if (! $group) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('groups/form', [
            'title' => 'Editar grupo',
            'group' => $group,
        ]);
    }

    public function atualizar(int $id)
    {
        $rules = [
            'name' => 'required|min_length[3]|is_unique[groups.name,id,' . $id . ']',
            'description' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new GroupModel();
        $model->update($id, [
            'name' => strtolower((string) $this->request->getPost('name')),
            'description' => $this->request->getPost('description'),
        ]);

        return redirect()->to('/grupos')->with('success', 'Grupo atualizado com sucesso.');
    }
}
