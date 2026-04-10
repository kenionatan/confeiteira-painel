<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.3.2/dist/css/tabler.min.css">
    <style>
        .domain-addon { min-width: 160px; }
        .mp-container {
            height: 40px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 8px 10px;
            background: #fff;
        }
    </style>
</head>
<body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-4">Criar conta</h2>

                    <?php if (session()->getFlashdata('errors')): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                                    <li><?= esc($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if (($gateway ?? 'mercado_pago') === 'mercado_pago' && empty($mercadoPagoPublicKey)): ?>
                        <div class="alert alert-warning">
                            Configure a chave publica do Mercado Pago para habilitar o cadastro com cartao.
                        </div>
                    <?php endif; ?>
                    <?php if (($gateway ?? '') === 'stripe' && empty($stripePublicKey ?? '')): ?>
                        <div class="alert alert-warning">
                            Configure a chave publica do Stripe para habilitar a captura de cartao.
                        </div>
                    <?php endif; ?>

                    <form method="post" action="/painel/cadastro" id="signup-form">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Dominio</label>
                            <div class="input-group">
                                <input type="text" name="dominio" value="<?= esc(old('dominio')) ?>" class="form-control" placeholder="minha-confeitaria" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                                <span class="input-group-text domain-addon">.appdoce.top</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="name" value="<?= esc(old('name')) ?>" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">WhatsApp</label>
                            <input type="text" name="whatsapp" value="<?= esc(old('whatsapp')) ?>" class="form-control" required placeholder="(11) 99999-9999">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" value="<?= esc(old('email')) ?>" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmar senha</label>
                            <input type="password" name="password_confirm" class="form-control" required>
                        </div>

                        <?php if (($gateway ?? 'mercado_pago') === 'mercado_pago'): ?>
                            <hr class="my-4">
                            <h3 class="h4 mb-3">Cartao para validacao do plano Free</h3>
                            <p class="text-secondary mb-3">Nenhuma cobranca sera feita agora. O cadastro do cartao e obrigatorio para concluir.</p>

                            <div class="mb-3">
                                <label class="form-label">Numero do cartao</label>
                                <div id="form-checkout__cardNumber" class="mp-container"></div>
                            </div>
                            <div class="row g-2">
                                <div class="col-8">
                                    <label class="form-label">Validade</label>
                                    <div id="form-checkout__expirationDate" class="mp-container"></div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">CVV</label>
                                    <div id="form-checkout__securityCode" class="mp-container"></div>
                                </div>
                            </div>
                            <div class="mb-3 mt-2">
                                <label class="form-label">Nome no cartao</label>
                                <input type="text" id="form-checkout__cardholderName" class="form-control" required>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">Tipo documento</label>
                                    <select id="form-checkout__identificationType" class="form-select" required>
                                        <option value="CPF">CPF</option>
                                        <option value="CNPJ">CNPJ</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Documento</label>
                                    <input type="text" id="form-checkout__identificationNumber" class="form-control" required>
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-6">
                                    <label class="form-label">Banco emissor</label>
                                    <select id="form-checkout__issuer" class="form-select" required></select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Parcelas</label>
                                    <select id="form-checkout__installments" class="form-select" required></select>
                                </div>
                            </div>
                            <input type="hidden" id="form-checkout__cardholderEmail" value="">
                        <?php else: ?>
                            <hr class="my-4">
                            <h3 class="h4 mb-3">Cartao para validacao do plano Free</h3>
                            <p class="text-secondary mb-3">Nenhuma cobranca sera feita agora. Vamos apenas validar e armazenar o metodo de pagamento.</p>
                            <div class="mb-3">
                                <label class="form-label">Cartao</label>
                                <div id="stripe-card-element" class="mp-container"></div>
                            </div>
                            <div class="small text-secondary">Dados protegidos pelo Stripe Elements.</div>
                        <?php endif; ?>

                        <input type="hidden" name="mp_card_token" id="mp_card_token" value="<?= esc(old('mp_card_token')) ?>">
                        <input type="hidden" name="mp_payment_method_id" id="mp_payment_method_id" value="<?= esc(old('mp_payment_method_id')) ?>">
                        <input type="hidden" name="mp_last_four_digits" id="mp_last_four_digits" value="<?= esc(old('mp_last_four_digits')) ?>">
                        <div class="alert alert-danger mt-3 d-none" id="card-error"></div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" <?= ((($gateway ?? 'mercado_pago') === 'mercado_pago' && empty($mercadoPagoPublicKey)) || (($gateway ?? '') === 'stripe' && empty($stripePublicKey ?? ''))) ? 'disabled' : '' ?>>Cadastrar</button>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <a href="/painel/login" class="btn btn-outline-secondary w-100">Voltar para login</a>
                </div>
            </div>
        </div>
    </div>
    <?php if (($gateway ?? 'mercado_pago') === 'mercado_pago'): ?>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script>
        (() => {
            const publicKey = '<?= esc($mercadoPagoPublicKey ?? '') ?>';
            const form = document.getElementById('signup-form');
            const errorEl = document.getElementById('card-error');
            if (!form || !publicKey) {
                return;
            }

            const mp = new MercadoPago(publicKey, { locale: 'pt-BR' });
            const tokenInput = document.getElementById('mp_card_token');
            const methodInput = document.getElementById('mp_payment_method_id');
            const last4Input = document.getElementById('mp_last_four_digits');
            const emailInput = form.querySelector('input[name="email"]');
            const cardholderEmailInput = document.getElementById('form-checkout__cardholderEmail');

            const showError = (message) => {
                errorEl.classList.remove('d-none');
                errorEl.textContent = message;
            };

            const onlyDigits = (value) => (value || '').replace(/\D+/g, '');

            if (emailInput && cardholderEmailInput) {
                const syncEmail = () => { cardholderEmailInput.value = emailInput.value.trim(); };
                emailInput.addEventListener('input', syncEmail);
                syncEmail();
            }

            const cardForm = mp.cardForm({
                amount: '1',
                iframe: true,
                autoMount: true,
                form: {
                    id: 'signup-form',
                    cardNumber: { id: 'form-checkout__cardNumber', placeholder: '5031 4332 1540 6351' },
                    expirationDate: { id: 'form-checkout__expirationDate', placeholder: 'MM/YY' },
                    securityCode: { id: 'form-checkout__securityCode', placeholder: '123' },
                    cardholderName: { id: 'form-checkout__cardholderName', placeholder: 'Nome no cartao' },
                    issuer: { id: 'form-checkout__issuer' },
                    installments: { id: 'form-checkout__installments' },
                    identificationType: { id: 'form-checkout__identificationType' },
                    identificationNumber: { id: 'form-checkout__identificationNumber', placeholder: 'CPF do titular' },
                    cardholderEmail: { id: 'form-checkout__cardholderEmail' },
                },
                callbacks: {
                    onFormMounted: (error) => {
                        if (error) {
                            showError('Falha ao iniciar formulario de cartao.');
                        }
                    },
                    onSubmit: (event) => {
                        event.preventDefault();
                        errorEl.classList.add('d-none');
                        const data = cardForm.getCardFormData();
                        if (!data.token) {
                            showError('Nao foi possivel gerar token do cartao.');
                            return;
                        }
                        if (!data.paymentMethodId) {
                            showError('Nao foi possivel identificar a bandeira do cartao. Digite novamente o numero.');
                            return;
                        }

                        tokenInput.value = data.token;
                        methodInput.value = data.paymentMethodId || 'desconhecido';
                        last4Input.value = '0000';
                        form.submit();
                    },
                    onError: (error) => {
                        const mpMessage =
                            error?.message ||
                            error?.cause?.[0]?.description ||
                            error?.cause?.[0]?.message ||
                            'Cartao invalido ou nao autorizado para teste.';
                        showError(mpMessage);
                    },
                },
            });

            form.addEventListener('submit', () => {
                if (!tokenInput.value && cardholderEmailInput) {
                    cardholderEmailInput.value = emailInput?.value?.trim() || '';
                }
            });
        })();
    </script>
    <?php elseif (($gateway ?? '') === 'stripe'): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        (() => {
            const publicKey = '<?= esc($stripePublicKey ?? '') ?>';
            const form = document.getElementById('signup-form');
            const errorEl = document.getElementById('card-error');
            const tokenInput = document.getElementById('mp_card_token');
            const methodInput = document.getElementById('mp_payment_method_id');
            const last4Input = document.getElementById('mp_last_four_digits');
            const emailInput = form?.querySelector('input[name="email"]');

            if (!form || !publicKey) return;

            const stripe = Stripe(publicKey);
            const elements = stripe.elements();
            const card = elements.create('card', {
                hidePostalCode: true,
            });
            card.mount('#stripe-card-element');

            const showError = (message) => {
                errorEl.classList.remove('d-none');
                errorEl.textContent = message;
            };

            card.on('change', (event) => {
                if (event.error) {
                    showError(event.error.message || 'Dados de cartao invalidos.');
                    return;
                }
                errorEl.classList.add('d-none');
            });

            form.addEventListener('submit', async (event) => {
                if (tokenInput.value) return;
                event.preventDefault();
                errorEl.classList.add('d-none');

                const billingName = (form.querySelector('input[name="name"]')?.value || '').trim();
                const billingEmail = (emailInput?.value || '').trim();

                const result = await stripe.createPaymentMethod({
                    type: 'card',
                    card,
                    billing_details: {
                        name: billingName,
                        email: billingEmail,
                    },
                });

                if (result.error || !result.paymentMethod) {
                    showError(result.error?.message || 'Nao foi possivel validar o cartao no Stripe.');
                    return;
                }

                tokenInput.value = result.paymentMethod.id;
                methodInput.value = result.paymentMethod.card?.brand || 'stripe';
                last4Input.value = result.paymentMethod.card?.last4 || '0000';
                form.submit();
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>

