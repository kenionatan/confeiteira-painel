<?php

namespace App\Controllers;

use App\Models\GroupModel;
use App\Models\UserGroupModel;
use App\Models\UserModel;

class UsersController extends BaseController
{
    public function index(): string
    {
        $db = \Config\Database::connect();
        $users = $db->query("
            SELECT u.*, COALESCE(GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ', '), '') AS groups_names
            FROM users u
            LEFT JOIN user_groups ug ON ug.user_id = u.id
            LEFT JOIN `groups` g ON g.id = ug.group_id
            GROUP BY u.id
            ORDER BY u.id DESC
        ")->getResultArray();

        return view('users/index', [
            'title' => 'Usuarios',
            'users' => $users,
        ]);
    }

    public function novo(): string
    {
        $groupModel = new GroupModel();
        return view('users/form', [
            'title' => 'Novo usuario',
            'user' => null,
            'groups' => $groupModel->orderBy('name', 'ASC')->findAll(),
            'selectedGroupIds' => [],
        ]);
    }

    public function salvar()
    {
        $rules = [
            'name' => 'required|min_length[3]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]',
            'password_confirm' => 'required|matches[password]',
            'preferred_theme' => 'required|in_list[light,dark]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userModel = new UserModel();
        $userId = $userModel->insert([
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'preferred_theme' => $this->request->getPost('preferred_theme'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ], true);

        $this->syncGroups((int) $userId, (array) $this->request->getPost('group_ids'));

        return redirect()->to('/usuarios')->with('success', 'Usuario cadastrado com sucesso.');
    }

    public function editar(int $id): string
    {
        $userModel = new UserModel();
        $groupModel = new GroupModel();
        $userGroupModel = new UserGroupModel();
        $user = $userModel->find($id);

        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('users/form', [
            'title' => 'Editar usuario',
            'user' => $user,
            'groups' => $groupModel->orderBy('name', 'ASC')->findAll(),
            'selectedGroupIds' => array_map(
                static fn(array $row): int => (int) $row['group_id'],
                $userGroupModel->where('user_id', $id)->findAll()
            ),
        ]);
    }

    public function atualizar(int $id)
    {
        $rules = [
            'name' => 'required|min_length[3]',
            'email' => 'required|valid_email|is_unique[users.email,id,' . $id . ']',
            'preferred_theme' => 'required|in_list[light,dark]',
        ];

        if ($this->request->getPost('password')) {
            $rules['password'] = 'min_length[6]';
            $rules['password_confirm'] = 'matches[password]';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userModel = new UserModel();
        $payload = [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'preferred_theme' => $this->request->getPost('preferred_theme'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
        if ($this->request->getPost('password')) {
            $payload['password_hash'] = password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT);
        }
        $userModel->update($id, $payload);

        $this->syncGroups($id, (array) $this->request->getPost('group_ids'));

        return redirect()->to('/usuarios')->with('success', 'Usuario atualizado com sucesso.');
    }

    private function syncGroups(int $userId, array $groupIds): void
    {
        $cleanIds = array_values(array_unique(array_map('intval', $groupIds)));
        $cleanIds = array_filter($cleanIds, static fn(int $id): bool => $id > 0);

        $userGroupModel = new UserGroupModel();
        $userGroupModel->where('user_id', $userId)->delete();

        if (empty($cleanIds)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = array_map(
            static fn(int $groupId): array => [
                'user_id' => $userId,
                'group_id' => $groupId,
                'created_at' => $now,
            ],
            $cleanIds
        );
        $userGroupModel->insertBatch($rows);
    }
}
