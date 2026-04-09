<?php

namespace App\Controllers;

use App\Models\ClienteModel;
use App\Models\PlanModel;
use App\Models\SubscriptionModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

class AuthController extends BaseController
{
    public function login(): string
    {
        if (session()->get('user_id')) {
            return redirect()->to('/painel');
        }

        return view('auth/login', ['title' => 'Login']);
    }

    public function authenticate()
    {
        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userModel = new UserModel();
        $user = $userModel->where('email', $this->request->getPost('email'))->first();

        if (! $user || ! password_verify((string) $this->request->getPost('password'), $user['password_hash'])) {
            return redirect()->back()->withInput()->with('errors', ['Credenciais invalidas.']);
        }

        if (! (int) $user['is_active']) {
            return redirect()->back()->withInput()->with('errors', ['Usuario inativo.']);
        }

        session()->set([
            'user_id' => $user['id'],
            'theme'   => $user['preferred_theme'] ?? 'light',
        ]);

        return redirect()->to('/painel')->with('success', 'Login realizado com sucesso.');
    }

    public function register(): string
    {
        $subscriptions = config('Subscriptions');
        return view('auth/register', [
            'title' => 'Cadastro',
            'mercadoPagoPublicKey' => $subscriptions->mercadoPagoPublicKey,
        ]);
    }

    public function store()
    {
        $rules = [
            'dominio' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]|min_length[3]|max_length[63]',
            'name' => 'required|min_length[3]|max_length[150]',
            'whatsapp' => 'required|min_length[10]|max_length[30]',
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]',
            'password_confirm' => 'required|matches[password]',
            'mp_card_token' => 'required',
            'mp_payment_method_id' => 'required',
            'mp_last_four_digits' => 'required|exact_length[4]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $dominio = strtolower(trim((string) $this->request->getPost('dominio')));
        $dominioCompleto = $dominio . '.appdoce.top';
        $email = strtolower(trim((string) $this->request->getPost('email')));

        $clienteModel = new ClienteModel();
        $userModel = new UserModel();
        $planModel = new PlanModel();
        $subscriptionModel = new SubscriptionModel();

        if ($clienteModel->where('dominio', $dominioCompleto)->first()) {
            return redirect()->back()->withInput()->with('errors', ['Dominio ja cadastrado.']);
        }
        if ($clienteModel->where('email', $email)->first()) {
            return redirect()->back()->withInput()->with('errors', ['Email ja cadastrado em cliente.']);
        }
        if ($userModel->where('email', $email)->first()) {
            return redirect()->back()->withInput()->with('errors', ['Email ja cadastrado para acesso.']);
        }

        $senhaHash = password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT);
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $clienteId = $clienteModel->insert([
                'dominio' => $dominioCompleto,
                'nome' => $this->request->getPost('name'),
                'whatsapp' => $this->request->getPost('whatsapp'),
                'email' => $email,
                'senha_hash' => $senhaHash,
                'cartao_token' => $this->request->getPost('mp_card_token'),
                'cartao_ultimos4' => $this->request->getPost('mp_last_four_digits'),
                'cartao_bandeira' => $this->request->getPost('mp_payment_method_id'),
            ], true);

            $userModel->insert([
                'cliente_id' => $clienteId,
                'name' => $this->request->getPost('name'),
                'email' => $email,
                'password_hash' => $senhaHash,
                'preferred_theme' => 'light',
                'is_owner' => 1,
                'is_active' => 1,
            ]);

            $freePlan = $planModel->where('slug', 'free')->first();
            if ($freePlan) {
                $subscriptionModel->insert([
                    'cliente_id' => $clienteId,
                    'plan_id' => $freePlan['id'],
                    'status' => 'active',
                    'gateway' => 'mercado_pago',
                    'gateway_subscription_id' => null,
                    'started_at' => date('Y-m-d H:i:s'),
                    'next_billing_at' => null,
                    'ends_at' => null,
                ]);
            }
        } catch (DatabaseException $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('errors', ['Falha ao concluir cadastro. Tente novamente.']);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('errors', ['Falha ao concluir cadastro. Tente novamente.']);
        }

        return redirect()->to('/painel/login')->with('success', 'Conta criada com sucesso. Faca login.');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/painel/login')->with('success', 'Logout realizado.');
    }
}

