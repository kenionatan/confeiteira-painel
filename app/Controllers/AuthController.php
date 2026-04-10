<?php

namespace App\Controllers;

use App\Models\ClienteModel;
use App\Models\PlanModel;
use App\Models\SubscriptionModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Stripe\StripeClient;

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
            'stripePublicKey' => $subscriptions->stripePublicKey,
            'gateway' => $subscriptions->gateway,
        ]);
    }

    public function store()
    {
        $subscriptions = config('Subscriptions');
        $isMercadoPago = $subscriptions->gateway === 'mercado_pago';
        $isStripe = $subscriptions->gateway === 'stripe';

        $rules = [
            'dominio' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]|min_length[3]|max_length[63]',
            'name' => 'required|min_length[3]|max_length[150]',
            'whatsapp' => 'required|min_length[10]|max_length[30]',
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]',
            'password_confirm' => 'required|matches[password]',
            'mp_card_token' => ($isMercadoPago || $isStripe) ? 'required' : 'permit_empty',
            'mp_payment_method_id' => ($isMercadoPago || $isStripe) ? 'required' : 'permit_empty',
            'mp_last_four_digits' => 'permit_empty|exact_length[4]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $dominio = strtolower(trim((string) $this->request->getPost('dominio')));
        $dominioCompleto = $dominio . '.appdoce.top';
        $email = strtolower(trim((string) $this->request->getPost('email')));

        $clienteModel = new ClienteModel();
        $planModel = new PlanModel();
        $subscriptionModel = new SubscriptionModel();

        if ($clienteModel->where('dominio', $dominioCompleto)->first()) {
            return redirect()->back()->withInput()->with('errors', ['Dominio ja cadastrado.']);
        }
        if ($clienteModel->where('email', $email)->first()) {
            return redirect()->back()->withInput()->with('errors', ['Email ja cadastrado em cliente.']);
        }

        $cardToken = (string) ($this->request->getPost('mp_card_token') ?: '');
        $cardBrand = (string) ($this->request->getPost('mp_payment_method_id') ?: '');
        $cardLast4 = (string) ($this->request->getPost('mp_last_four_digits') ?: '0000');
        $stripeCustomerId = null;

        if ($isStripe) {
            if ($subscriptions->stripeSecretKey === '') {
                return redirect()->back()->withInput()->with('errors', ['Stripe nao configurado no servidor.']);
            }

            try {
                $stripe = new StripeClient($subscriptions->stripeSecretKey);
                $paymentMethod = $stripe->paymentMethods->retrieve($cardToken, []);
                if (($paymentMethod->type ?? '') !== 'card') {
                    return redirect()->back()->withInput()->with('errors', ['Metodo de pagamento invalido para cadastro.']);
                }

                $cardBrand = (string) ($paymentMethod->card->brand ?? $cardBrand ?: 'stripe');
                $cardLast4 = (string) ($paymentMethod->card->last4 ?? $cardLast4);

                $customer = $stripe->customers->create([
                    'email' => $email,
                    'name' => (string) $this->request->getPost('name'),
                    'metadata' => [
                        'dominio' => $dominioCompleto,
                    ],
                ]);
                $stripeCustomerId = $customer->id;

                $stripe->paymentMethods->attach($cardToken, ['customer' => $stripeCustomerId]);

                $setupIntent = $stripe->setupIntents->create([
                    'customer' => $stripeCustomerId,
                    'payment_method' => $cardToken,
                    'confirm' => true,
                    'payment_method_types' => ['card'],
                    'usage' => 'off_session',
                ]);

                if (($setupIntent->status ?? '') !== 'succeeded') {
                    return redirect()->back()->withInput()->with('errors', [
                        'Nao foi possivel confirmar o cartao. Status: ' . ($setupIntent->status ?? 'desconhecido'),
                    ]);
                }
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('errors', ['Nao foi possivel validar o cartao no Stripe. Tente novamente.']);
            }
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
                'stripe_customer_id' => $stripeCustomerId,
                'senha_hash' => $senhaHash,
                'cartao_token' => $cardToken !== '' ? $cardToken : 'pending_gateway',
                'cartao_ultimos4' => $cardLast4 !== '' ? $cardLast4 : '0000',
                'cartao_bandeira' => $cardBrand !== '' ? $cardBrand : $subscriptions->gateway,
            ], true);

            $freePlan = $planModel->where('slug', 'free')->first();
            if ($freePlan) {
                $subscriptionModel->insert([
                    'cliente_id' => $clienteId,
                    'plan_id' => $freePlan['id'],
                    'status' => 'active',
                    'gateway' => $subscriptions->gateway,
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

        return redirect()->to('/painel/cadastro/obrigado')->with('register_email', $email);
    }

    public function registerSuccess(): string
    {
        return view('auth/register_success', [
            'title' => 'Cadastro recebido',
            'email' => session()->getFlashdata('register_email'),
        ]);
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/painel/login')->with('success', 'Logout realizado.');
    }
}

