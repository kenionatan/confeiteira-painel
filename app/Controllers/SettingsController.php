<?php

namespace App\Controllers;

use App\Models\AppSettingModel;
use App\Models\UserModel;

class SettingsController extends BaseController
{
    public function index(): string
    {
        $settingsModel = new AppSettingModel();
        $settings = $settingsModel->first();

        return view('settings/index', [
            'title' => 'Configuracoes',
            'settings' => $settings,
            'currentUser' => current_user(),
        ]);
    }

    public function salvar()
    {
        $rules = [
            'app_name' => 'required|min_length[3]|max_length[120]',
            'title_color' => 'required|regex_match[/^#[0-9A-Fa-f]{6}$/]',
            'preferred_theme' => 'required|in_list[light,dark]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $settingsModel = new AppSettingModel();
        $settings = $settingsModel->first();
        if (! $settings) {
            $settingsModel->insert([
                'app_name' => $this->request->getPost('app_name'),
                'title_color_enabled' => 1,
                'title_color' => $this->request->getPost('title_color'),
            ]);
        } else {
            $settingsModel->update($settings['id'], [
                'app_name' => $this->request->getPost('app_name'),
                'title_color_enabled' => 1,
                'title_color' => $this->request->getPost('title_color'),
            ]);
        }

        $user = current_user();
        if ($user) {
            $userModel = new UserModel();
            $theme = (string) $this->request->getPost('preferred_theme');
            $userModel->update($user['id'], ['preferred_theme' => $theme]);
            session()->set('theme', $theme);
        }

        return redirect()->to('/configuracoes')->with('success', 'Configuracoes salvas com sucesso.');
    }

    public function trocarTema()
    {
        $rules = [
            'preferred_theme' => 'required|in_list[light,dark]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $user = current_user();
        if ($user) {
            $theme = (string) $this->request->getPost('preferred_theme');
            $userModel = new UserModel();
            $userModel->update($user['id'], ['preferred_theme' => $theme]);
            session()->set('theme', $theme);
        }

        return redirect()->back();
    }

    public function trocarTemaAjax()
    {
        $theme = (string) $this->request->getPost('preferred_theme');
        if (! in_array($theme, ['light', 'dark'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok' => false,
                'message' => 'Tema invalido.',
            ]);
        }

        $user = current_user();
        if (! $user) {
            return $this->response->setStatusCode(401)->setJSON([
                'ok' => false,
                'message' => 'Nao autenticado.',
            ]);
        }

        $userModel = new UserModel();
        $userModel->update($user['id'], ['preferred_theme' => $theme]);
        session()->set('theme', $theme);

        return $this->response->setJSON([
            'ok' => true,
            'theme' => $theme,
        ]);
    }
}
