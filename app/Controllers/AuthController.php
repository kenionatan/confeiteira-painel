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
        $planSlug = strtolower(trim((string) $this->request->getGet('plano')));
        if ($planSlug === '') {
            $planSlug = 'free';
        }

        $planModel = new PlanModel();
        $selectedPlan = $planModel->where('slug', $planSlug)->where('ativo', 1)->first();
        if (! $selectedPlan) {
            $planSlug = 'free';
            $selectedPlan = $planModel->where('slug', 'free')->where('ativo', 1)->first();
        }

        $isPaidPlan = $planSlug !== 'free' && (float) ($selectedPlan['valor_mensal'] ?? 0) > 0;
        $stripePriceId = $subscriptions->stripePriceIdForPlanSlug($planSlug);
        $paidStripeReady = $subscriptions->gateway === 'stripe'
            && $isPaidPlan
            && $stripePriceId !== ''
            && str_starts_with($stripePriceId, 'price_');

        return view('auth/register', [
            'title'             => 'Cadastro',
            'mercadoPagoPublicKey' => $subscriptions->mercadoPagoPublicKey,
            'stripePublicKey'   => $subscriptions->stripePublicKey,
            'gateway'           => $subscriptions->gateway,
            'planSlug'          => $planSlug,
            'selectedPlan'      => $selectedPlan,
            'isPaidPlan'        => $isPaidPlan,
            'isPaidStripe'      => $paidStripeReady,
            'stripePriceId'     => $stripePriceId,
        ]);
    }

    public function store()
    {
        $subscriptions = config('Subscriptions');
        $planSlug = strtolower(trim((string) $this->request->getPost('plan_slug')));
        if ($planSlug === '') {
            $planSlug = 'free';
        }

        if ($subscriptions->gateway === 'stripe' && in_array($planSlug, ['basico', 'pro'], true)) {
            return redirect()->back()->withInput()->with('errors', [
                'O cadastro deste plano exige confirmacao do pagamento no navegador. Verifique se o JavaScript esta habilitado e tente novamente.',
            ]);
        }

        $isMercadoPago = $subscriptions->gateway === 'mercado_pago';
        $isStripe = $subscriptions->gateway === 'stripe';

        if ($isMercadoPago && in_array($planSlug, ['basico', 'pro'], true)) {
            return redirect()->back()->withInput()->with('errors', [
                'Cadastro de planos pagos esta disponivel apenas com o gateway Stripe. Escolha o plano Free ou entre em contato.',
            ]);
        }

        $rules = [
            'plan_slug' => 'permit_empty|in_list[free,basico,pro]',
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

        $planModel = new PlanModel();
        $planRow = $planModel->where('slug', $planSlug)->where('ativo', 1)->first();
        if (! $planRow) {
            return redirect()->back()->withInput()->with('errors', ['Plano invalido.']);
        }

        $dominio = strtolower(trim((string) $this->request->getPost('dominio')));
        $dominioCompleto = $dominio . '.appdoce.top';
        $email = strtolower(trim((string) $this->request->getPost('email')));

        $clienteModel = new ClienteModel();
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

            $subscriptionModel->insert([
                'cliente_id' => $clienteId,
                'plan_id' => $planRow['id'],
                'status' => 'active',
                'gateway' => $subscriptions->gateway,
                'gateway_subscription_id' => null,
                'started_at' => date('Y-m-d H:i:s'),
                'next_billing_at' => null,
                'ends_at' => null,
            ]);
        } catch (DatabaseException $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('errors', ['Falha ao concluir cadastro. Tente novamente.']);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('errors', ['Falha ao concluir cadastro. Tente novamente.']);
        }

        return redirect()->to('/painel/cadastro/obrigado')
            ->with('register_email', $email)
            ->with('register_plan_nome', $planRow['nome'] ?? 'Free')
            ->with('register_plan_slug', $planSlug);
    }

    /**
     * Cria cliente Stripe, assinatura incompleta e devolve client_secret do primeiro pagamento (JSON).
     */
    public function paymentPrepare()
    {
        $subscriptions = config('Subscriptions');
        if ($subscriptions->gateway !== 'stripe' || $subscriptions->stripeSecretKey === '') {
            return $this->jsonError('Stripe nao configurado.', 503);
        }

        $rules = [
            'plan_slug' => 'required|in_list[basico,pro]',
            'dominio' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]|min_length[3]|max_length[63]',
            'name' => 'required|min_length[3]|max_length[150]',
            'whatsapp' => 'required|min_length[10]|max_length[30]',
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]',
            'password_confirm' => 'required|matches[password]',
            'mp_card_token' => 'required',
            'mp_payment_method_id' => 'permit_empty|max_length[80]',
            'mp_last_four_digits' => 'permit_empty|exact_length[4]',
        ];

        if (! $this->validate($rules)) {
            return $this->jsonError('Dados invalidos.', 422, $this->validator->getErrors());
        }

        $planSlug = strtolower(trim((string) $this->request->getPost('plan_slug')));
        $priceId = $subscriptions->stripePriceIdForPlanSlug($planSlug);
        if ($priceId === '' || ! str_starts_with($priceId, 'price_')) {
            return $this->jsonError('Price ID do Stripe nao configurado para este plano.', 422);
        }

        $planModel = new PlanModel();
        $planRow = $planModel->where('slug', $planSlug)->where('ativo', 1)->first();
        if (! $planRow) {
            return $this->jsonError('Plano invalido.', 422);
        }

        $dominio = strtolower(trim((string) $this->request->getPost('dominio')));
        $dominioCompleto = $dominio . '.appdoce.top';
        $email = strtolower(trim((string) $this->request->getPost('email')));

        $clienteModel = new ClienteModel();
        if ($clienteModel->where('dominio', $dominioCompleto)->first()) {
            return $this->jsonError('Dominio ja cadastrado.', 422);
        }
        if ($clienteModel->where('email', $email)->first()) {
            return $this->jsonError('Email ja cadastrado.', 422);
        }

        $pmId = (string) $this->request->getPost('mp_card_token');

        try {
            $stripe = new StripeClient($subscriptions->stripeSecretKey);
            $pm = $stripe->paymentMethods->retrieve($pmId, []);
            if (($pm->type ?? '') !== 'card') {
                return $this->jsonError('Metodo de pagamento invalido.', 422);
            }

            $customer = $stripe->customers->create([
                'email' => $email,
                'name' => (string) $this->request->getPost('name'),
                'metadata' => [
                    'dominio' => $dominioCompleto,
                    'plan_slug' => $planSlug,
                ],
            ]);

            $stripe->paymentMethods->attach($pmId, ['customer' => $customer->id]);

            $subscription = $stripe->subscriptions->create([
                'customer' => $customer->id,
                'items' => [['price' => $priceId]],
                'default_payment_method' => $pmId,
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription',
                ],
                'metadata' => [
                    'signup_email' => $email,
                    'signup_dominio' => $dominioCompleto,
                    'plan_slug' => $planSlug,
                ],
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            $subscription = $stripe->subscriptions->retrieve($subscription->id, [
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            for ($attempt = 0; $attempt < 8; $attempt++) {
                [, , $pi] = $this->resolveStripeInvoiceAndPaymentIntent($stripe, $subscription, $pmId);
                $subscription = $stripe->subscriptions->retrieve($subscription->id, [
                    'expand' => ['latest_invoice.payment_intent'],
                ]);
                if (in_array($subscription->status ?? '', ['active', 'trialing'], true)) {
                    return $this->response->setJSON([
                        'clientSecret' => null,
                        'subscriptionId' => $subscription->id,
                    ]);
                }
                if (is_object($pi)) {
                    break;
                }
                usleep(400000);
            }

            if (! is_object($pi)) {
                $hint = ENVIRONMENT !== 'production'
                    ? ' Verifique no Stripe Dashboard se o Price e recorrente, em BRL, e ligado ao produto correto.'
                    : '';

                return $this->jsonError('Nao foi possivel obter o pagamento da primeira fatura no Stripe.' . $hint, 422);
            }

            if (($pi->status ?? '') === 'requires_payment_method') {
                return $this->jsonError('Cartao recusado ou pagamento nao autorizado. Verifique os dados ou use outro cartao.', 402);
            }

            if (($pi->status ?? '') === 'requires_confirmation') {
                try {
                    $pi = $stripe->paymentIntents->confirm($pi->id, [
                        'payment_method' => $pmId,
                    ]);
                } catch (\Stripe\Exception\CardException $e) {
                    return $this->jsonError('Cartao recusado: ' . $e->getMessage(), 402);
                } catch (\Throwable) {
                    // Ex.: authentication_required — segue para client_secret no navegador (3DS)
                }
            }

            $subscription = $stripe->subscriptions->retrieve($subscription->id, [
                'expand' => ['latest_invoice.payment_intent'],
            ]);
            if (in_array($subscription->status ?? '', ['active', 'trialing'], true)) {
                return $this->response->setJSON([
                    'clientSecret' => null,
                    'subscriptionId' => $subscription->id,
                ]);
            }

            [, , $pi] = $this->resolveStripeInvoiceAndPaymentIntent($stripe, $subscription, $pmId);

            if (is_object($pi)) {
                if (($pi->status ?? '') === 'requires_payment_method') {
                    return $this->jsonError('Cartao recusado ou pagamento nao autorizado. Verifique os dados ou use outro cartao.', 402);
                }
                if (! empty($pi->client_secret) && in_array($pi->status, ['requires_action', 'requires_confirmation'], true)) {
                    return $this->response->setJSON([
                        'clientSecret' => $pi->client_secret,
                        'subscriptionId' => $subscription->id,
                    ]);
                }
                if (in_array($pi->status ?? '', ['processing', 'succeeded'], true)) {
                    return $this->response->setJSON([
                        'clientSecret' => null,
                        'subscriptionId' => $subscription->id,
                    ]);
                }
            }

            $detail = '';
            if (ENVIRONMENT !== 'production' && is_object($pi)) {
                $detail = ' (PaymentIntent: ' . ($pi->status ?? '?') . ')';
            }

            return $this->jsonError('Nao foi possivel iniciar o pagamento da assinatura. Tente novamente.' . $detail, 422);
        } catch (\Throwable $e) {
            $msg = 'Falha ao criar assinatura no Stripe. Tente novamente.';
            if (ENVIRONMENT !== 'production') {
                $msg .= ' ' . $e->getMessage();
            }

            return $this->jsonError($msg, 502);
        }
    }

    /**
     * Grava cliente e assinatura apos pagamento confirmado no Stripe.
     */
    public function paymentConfirm()
    {
        $subscriptions = config('Subscriptions');
        if ($subscriptions->gateway !== 'stripe' || $subscriptions->stripeSecretKey === '') {
            return $this->jsonError('Stripe nao configurado.', 503);
        }

        $rules = [
            'plan_slug' => 'required|in_list[basico,pro]',
            'stripe_subscription_id' => 'required|max_length[120]',
            'dominio' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]|min_length[3]|max_length[63]',
            'name' => 'required|min_length[3]|max_length[150]',
            'whatsapp' => 'required|min_length[10]|max_length[30]',
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]',
            'password_confirm' => 'required|matches[password]',
            'mp_card_token' => 'required',
            'mp_payment_method_id' => 'permit_empty|max_length[80]',
            'mp_last_four_digits' => 'permit_empty|exact_length[4]',
        ];

        if (! $this->validate($rules)) {
            return $this->jsonError('Dados invalidos.', 422, $this->validator->getErrors());
        }

        $planSlug = strtolower(trim((string) $this->request->getPost('plan_slug')));
        $expectedPriceId = $subscriptions->stripePriceIdForPlanSlug($planSlug);
        if ($expectedPriceId === '' || ! str_starts_with($expectedPriceId, 'price_')) {
            return $this->jsonError('Configuracao de plano invalida.', 422);
        }

        $planModel = new PlanModel();
        $planRow = $planModel->where('slug', $planSlug)->where('ativo', 1)->first();
        if (! $planRow) {
            return $this->jsonError('Plano invalido.', 422);
        }

        $dominio = strtolower(trim((string) $this->request->getPost('dominio')));
        $dominioCompleto = $dominio . '.appdoce.top';
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $subId = trim((string) $this->request->getPost('stripe_subscription_id'));

        $clienteModel = new ClienteModel();
        if ($clienteModel->where('dominio', $dominioCompleto)->first()) {
            return $this->jsonError('Dominio ja cadastrado.', 409);
        }
        if ($clienteModel->where('email', $email)->first()) {
            return $this->jsonError('Email ja cadastrado.', 409);
        }

        try {
            $stripe = new StripeClient($subscriptions->stripeSecretKey);
            $stripeSub = $stripe->subscriptions->retrieve($subId, ['expand' => ['items.data.price']]);

            for ($poll = 0; $poll < 10; $poll++) {
                if (in_array($stripeSub->status ?? '', ['active', 'trialing'], true)) {
                    break;
                }
                if (($stripeSub->status ?? '') !== 'incomplete') {
                    break;
                }
                usleep(400000);
                $stripeSub = $stripe->subscriptions->retrieve($subId, ['expand' => ['items.data.price']]);
            }

            $meta = $stripeSub->metadata ?? null;
            $metaEmail = is_object($meta) ? (string) ($meta->signup_email ?? '') : '';
            $metaDom = is_object($meta) ? (string) ($meta->signup_dominio ?? '') : '';
            if (strtolower($metaEmail) !== $email || $metaDom !== $dominioCompleto) {
                return $this->jsonError('Dados da assinatura nao conferem com o cadastro.', 403);
            }

            $stripeStatus = (string) ($stripeSub->status ?? '');
            if (! in_array($stripeStatus, ['active', 'trialing'], true)) {
                return $this->jsonError('Assinatura ainda nao esta ativa. Conclua o pagamento ou tente novamente.', 402);
            }

            $items = $stripeSub->items->data ?? [];
            $priceOnSub = '';
            if ($items !== [] && isset($items[0]->price)) {
                $priceOnSub = (string) ($items[0]->price->id ?? '');
            }
            if ($priceOnSub !== $expectedPriceId) {
                return $this->jsonError('Plano da assinatura nao confere.', 403);
            }

            $customerId = is_string($stripeSub->customer ?? null) ? $stripeSub->customer : null;
            if ($customerId === null) {
                return $this->jsonError('Cliente Stripe invalido.', 422);
            }

            $customer = $stripe->customers->retrieve($customerId);
            $custEmail = strtolower(trim((string) ($customer->email ?? '')));
            if ($custEmail !== $email) {
                return $this->jsonError('Email do cliente Stripe nao confere.', 403);
            }

            $cardToken = (string) $this->request->getPost('mp_card_token');
            $cardBrand = (string) ($this->request->getPost('mp_payment_method_id') ?: 'stripe');
            $cardLast4 = (string) ($this->request->getPost('mp_last_four_digits') ?: '0000');

            $pm = $stripe->paymentMethods->retrieve($cardToken, []);
            if (($pm->type ?? '') === 'card') {
                $cardBrand = (string) ($pm->card->brand ?? $cardBrand);
                $cardLast4 = (string) ($pm->card->last4 ?? $cardLast4);
            }

            $dbStatus = $this->mapStripeSubscriptionStatus($stripeStatus);
            $periodEnd = isset($stripeSub->current_period_end) ? (int) $stripeSub->current_period_end : null;
            $started = isset($stripeSub->start_date) ? (int) $stripeSub->start_date : (isset($stripeSub->current_period_start) ? (int) $stripeSub->current_period_start : time());

            $senhaHash = password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT);
            $db = \Config\Database::connect();
            $db->transStart();
            $subscriptionModel = new SubscriptionModel();

            try {
                $clienteId = $clienteModel->insert([
                    'dominio' => $dominioCompleto,
                    'nome' => $this->request->getPost('name'),
                    'whatsapp' => $this->request->getPost('whatsapp'),
                    'email' => $email,
                    'stripe_customer_id' => $customerId,
                    'senha_hash' => $senhaHash,
                    'cartao_token' => $cardToken !== '' ? $cardToken : 'stripe',
                    'cartao_ultimos4' => $cardLast4 !== '' ? $cardLast4 : '0000',
                    'cartao_bandeira' => $cardBrand !== '' ? $cardBrand : 'stripe',
                ], true);

                $subscriptionModel->insert([
                    'cliente_id' => $clienteId,
                    'plan_id' => $planRow['id'],
                    'status' => $dbStatus,
                    'gateway' => 'stripe',
                    'gateway_subscription_id' => $subId,
                    'started_at' => date('Y-m-d H:i:s', $started),
                    'next_billing_at' => $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : null,
                    'ends_at' => null,
                ]);
            } catch (DatabaseException $e) {
                $db->transRollback();
                return $this->jsonError('Falha ao salvar cadastro. Entre em contato com o suporte.', 500);
            }

            $db->transComplete();
            if (! $db->transStatus()) {
                return $this->jsonError('Falha ao salvar cadastro.', 500);
            }

            session()->setFlashdata('register_email', $email);
            session()->setFlashdata('register_plan_nome', $planRow['nome'] ?? $planSlug);
            session()->setFlashdata('register_plan_slug', $planSlug);

            return $this->response->setJSON([
                'ok' => true,
                'redirect' => site_url('/painel/cadastro/obrigado'),
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError('Nao foi possivel validar a assinatura. Tente novamente.', 502);
        }
    }

    public function registerSuccess(): string
    {
        return view('auth/register_success', [
            'title' => 'Cadastro recebido',
            'email' => session()->getFlashdata('register_email'),
            'planNome' => session()->getFlashdata('register_plan_nome'),
            'planSlug' => session()->getFlashdata('register_plan_slug'),
        ]);
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/painel/login')->with('success', 'Logout realizado.');
    }

    /**
     * Resolve a primeira fatura da assinatura e o PaymentIntent (direto, via charge ou apos invoices->pay).
     *
     * @return array{0: object, 1: object|null, 2: object|null}
     */
    private function resolveStripeInvoiceAndPaymentIntent(StripeClient $stripe, object $subscription, ?string $paymentMethodId = null): array
    {
        $expandInv = ['expand' => ['payment_intent', 'charge']];

        $latest = $subscription->latest_invoice ?? null;
        if (is_string($latest) && $latest !== '') {
            $latest = $stripe->invoices->retrieve($latest, $expandInv);
        }
        if (! is_object($latest)) {
            $list = $stripe->invoices->all([
                'subscription' => $subscription->id,
                'limit' => 5,
            ]);
            foreach ($list->data ?? [] as $row) {
                if (is_object($row) && ! empty($row->id)) {
                    $latest = $stripe->invoices->retrieve($row->id, $expandInv);
                    break;
                }
            }
        }
        if (! is_object($latest)) {
            return [$subscription, null, null];
        }

        if (($latest->status ?? '') === 'draft') {
            try {
                $latest = $stripe->invoices->finalizeInvoice($latest->id, $expandInv);
            } catch (\Throwable) {
                // Mantem fatura atual se nao for possivel finalizar
            }
        }

        $pi = $this->stripeInvoiceExtractPaymentIntent($stripe, $latest);

        if (! is_object($pi) && $paymentMethodId !== null && $paymentMethodId !== ''
            && ($latest->status ?? '') === 'open'
            && (int) ($latest->amount_due ?? 0) > 0) {
            try {
                $latest = $stripe->invoices->pay($latest->id, array_merge([
                    'payment_method' => $paymentMethodId,
                ], $expandInv));
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'already') !== false || stripos($msg, 'paid') !== false) {
                    $latest = $stripe->invoices->retrieve($latest->id, $expandInv);
                } else {
                    try {
                        $latest = $stripe->invoices->retrieve($latest->id, $expandInv);
                    } catch (\Throwable) {
                        throw $e;
                    }
                }
            }
            $pi = $this->stripeInvoiceExtractPaymentIntent($stripe, $latest);
        }

        return [$subscription, $latest, is_object($pi) ? $pi : null];
    }

    private function stripeInvoiceExtractPaymentIntent(StripeClient $stripe, object $invoice): ?object
    {
        $pi = $invoice->payment_intent ?? null;
        if (is_string($pi) && $pi !== '') {
            return $stripe->paymentIntents->retrieve($pi);
        }
        if (is_object($pi)) {
            return $pi;
        }

        $chargeId = $invoice->charge ?? null;
        if (is_string($chargeId) && $chargeId !== '') {
            $charge = $stripe->charges->retrieve($chargeId, ['expand' => ['payment_intent']]);
            $cpi = $charge->payment_intent ?? null;
            if (is_string($cpi) && $cpi !== '') {
                return $stripe->paymentIntents->retrieve($cpi);
            }
            if (is_object($cpi)) {
                return $cpi;
            }
        }

        return null;
    }

    private function mapStripeSubscriptionStatus(string $stripe): string
    {
        return match ($stripe) {
            'trialing' => 'trial',
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled', 'unpaid' => 'cancelled',
            default => 'active',
        };
    }

    /**
     * @param array<string, string>|list<string> $errors
     */
    private function jsonError(string $message, int $status = 400, array $errors = [])
    {
        $payload = ['ok' => false, 'error' => $message];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return $this->response->setStatusCode($status)->setJSON($payload);
    }
}
