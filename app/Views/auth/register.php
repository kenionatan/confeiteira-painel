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
    <?php
    $planSlug = $planSlug ?? 'free';
    $selectedPlan = $selectedPlan ?? null;
    $isPaidPlan = ! empty($isPaidPlan);
    /** Price ID (price_...) configurado no .env para o plano atual — só aviso, não bloqueia o botão. */
    $priceIdsConfigured = ! empty($isPaidStripe);
    /** Plano pago + Stripe com pk: usa fluxo pagamento (pagamento + confirmar), mesmo sem Price ID (o servidor avisa). */
    $useStripePaidFlow = $isPaidPlan && ($gateway ?? '') === 'stripe' && ! empty($stripePublicKey ?? '');
    $planNome = $selectedPlan['nome'] ?? ucfirst($planSlug);
    $valorMensal = isset($selectedPlan['valor_mensal']) ? number_format((float) $selectedPlan['valor_mensal'], 2, ',', '.') : '0,00';
    $submitDisabled = (($gateway ?? '') === 'stripe' && empty($stripePublicKey ?? ''))
        || (($gateway ?? 'mercado_pago') === 'mercado_pago' && empty($mercadoPagoPublicKey ?? ''))
        || ($isPaidPlan && ($gateway ?? '') === 'mercado_pago');
    $stripePixEnabled = ! empty($stripePixEnabled);
    $showStripePixChoice = $useStripePaidFlow && $stripePixEnabled;
    ?>
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-2">Criar conta</h2>
                    <p class="text-center text-secondary mb-4">
                        Plano: <span class="badge bg-primary-lt"><?= esc($planNome) ?></span>
                        <?php if ($isPaidPlan): ?>
                            &mdash; <strong>R$ <?= esc($valorMensal) ?></strong> / mês
                        <?php else: ?>
                            &mdash; <strong>Grátis</strong>
                        <?php endif; ?>
                    </p>

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
                            Configure a chave pública do Mercado Pago para habilitar o cadastro com cartão.
                        </div>
                    <?php endif; ?>
                    <?php if (($gateway ?? '') === 'stripe' && empty($stripePublicKey ?? '')): ?>
                        <div class="alert alert-warning">
                            Configure a chave pública do Stripe para habilitar a captura de cartão.
                        </div>
                    <?php endif; ?>
                    <?php if ($isPaidPlan && ($gateway ?? '') === 'mercado_pago'): ?>
                        <div class="alert alert-warning">
                            Cadastro com planos pagos no momento só está disponível com o gateway Stripe. Escolha o plano Free ou altere <code>subscriptions.gateway</code> no .env.
                        </div>
                    <?php endif; ?>
                    <?php if ($isPaidPlan && ($gateway ?? '') === 'stripe' && ! $priceIdsConfigured): ?>
                        <div class="alert alert-warning">
                            Defina no .env o Price ID deste plano: <code>subscriptions.stripePriceBasico</code> ou <code>subscriptions.stripePricePro</code> (valor <code>price_...</code> do Stripe). Sem isso o pagamento não inicia.
                        </div>
                    <?php endif; ?>

                    <form method="post" action="/painel/cadastro" id="signup-form" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="plan_slug" value="<?= esc($planSlug) ?>">
                        <input type="hidden" name="stripe_subscription_id" id="stripe_subscription_id" value="">

                        <div class="mb-3">
                            <label class="form-label">Domínio</label>
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
                            <label class="form-label">E-mail</label>
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
                            <h3 class="h4 mb-3">Cartão</h3>
                            <?php if ($isPaidPlan): ?>
                                <p class="text-secondary mb-3">Plano pago: use o cadastro com Stripe (veja aviso acima).</p>
                            <?php else: ?>
                                <p class="text-secondary mb-3">Nenhuma cobrança será feita agora. O cadastro do cartão é obrigatório para concluir.</p>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Número do cartão</label>
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
                                <label class="form-label">Nome no cartão</label>
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
                            <h3 class="h4 mb-3">Pagamento</h3>
                            <?php if ($isPaidPlan && $useStripePaidFlow): ?>
                                <p class="text-secondary mb-3">
                                    Será cobrada a <strong>primeira mensalidade</strong> (R$ <?= esc($valorMensal) ?>) agora. Renovações automáticas no método escolhido (cartão ou PIX).
                                </p>
                            <?php else: ?>
                                <p class="text-secondary mb-3">Nenhuma cobrança será feita agora. Vamos apenas validar e armazenar o método de pagamento.</p>
                            <?php endif; ?>

                            <input type="hidden" name="payment_option" id="payment_option" value="card">
                            <?php if ($showStripePixChoice): ?>
                                <div class="mb-3">
                                    <label class="form-label">Forma de pagamento</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <label class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="stripe_pay_method" id="stripe-pay-card" value="card" checked>
                                            <span class="form-check-label">Cartão</span>
                                        </label>
                                        <label class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="stripe_pay_method" id="stripe-pay-pix" value="pix">
                                            <span class="form-check-label">PIX</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="alert alert-info d-none mb-3" id="stripe-pix-hint" role="status">
                                    Você verá o QR Code ou o código copia e cola. No app do banco, autorize o <strong>PIX Automático</strong> para as próximas cobranças. Ao voltar a esta página, concluímos o cadastro.
                                </div>
                            <?php endif; ?>

                            <div class="mb-3" id="stripe-card-section">
                                <label class="form-label">Cartão</label>
                                <div id="stripe-card-element" class="mp-container"></div>
                                <div class="small text-secondary mt-1">Dados protegidos pelo Stripe Elements.</div>
                            </div>
                        <?php endif; ?>

                        <input type="hidden" name="mp_card_token" id="mp_card_token" value="<?= esc(old('mp_card_token')) ?>">
                        <input type="hidden" name="mp_payment_method_id" id="mp_payment_method_id" value="<?= esc(old('mp_payment_method_id')) ?>">
                        <input type="hidden" name="mp_last_four_digits" id="mp_last_four_digits" value="<?= esc(old('mp_last_four_digits')) ?>">
                        <div class="alert alert-danger mt-3 d-none" id="card-error"></div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" id="signup-submit" <?= $submitDisabled ? 'disabled' : '' ?>>
                                <?= $isPaidPlan ? 'Cadastrar e pagar' : 'Cadastrar' ?>
                            </button>
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
                    cardholderName: { id: 'form-checkout__cardholderName', placeholder: 'Nome no cartão' },
                    issuer: { id: 'form-checkout__issuer' },
                    installments: { id: 'form-checkout__installments' },
                    identificationType: { id: 'form-checkout__identificationType' },
                    identificationNumber: { id: 'form-checkout__identificationNumber', placeholder: 'CPF do titular' },
                    cardholderEmail: { id: 'form-checkout__cardholderEmail' },
                },
                callbacks: {
                    onFormMounted: (error) => {
                        if (error) {
                            showError('Falha ao iniciar formulário de cartão.');
                        }
                    },
                    onSubmit: (event) => {
                        event.preventDefault();
                        errorEl.classList.add('d-none');
                        const data = cardForm.getCardFormData();
                        if (!data.token) {
                            showError('Não foi possível gerar token do cartão.');
                            return;
                        }
                        if (!data.paymentMethodId) {
                            showError('Não foi possível identificar a bandeira do cartão. Digite novamente o número.');
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
                            'Cartão inválido ou não autorizado para teste.';
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
            const subIdInput = document.getElementById('stripe_subscription_id');
            const paymentOptionInput = document.getElementById('payment_option');
            const emailInput = form?.querySelector('input[name="email"]');
            const useStripePaidFlow = <?= $useStripePaidFlow ? 'true' : 'false' ?>;
            const stripePixOffered = <?= $showStripePixChoice ? 'true' : 'false' ?>;

            if (!form || !publicKey) return;

            const stripe = Stripe(publicKey);

            const showError = (message) => {
                errorEl.classList.remove('d-none');
                errorEl.textContent = message;
            };

            const syncPaymentOptionFromUi = () => {
                const pix = document.getElementById('stripe-pay-pix');
                if (paymentOptionInput && pix) {
                    paymentOptionInput.value = pix.checked ? 'pix' : 'card';
                }
            };

            const isPixSelected = () =>
                stripePixOffered && document.getElementById('stripe-pay-pix')?.checked;

            const persistFormForPixResume = (fd) => {
                const plain = {};
                fd.forEach((v, k) => {
                    plain[k] = typeof v === 'string' ? v : '';
                });
                sessionStorage.setItem('stripe_pix_form', JSON.stringify(plain));
            };

            /** @returns {Promise<'redirect'|boolean>} false = seguir fluxo normal; true = erro no retorno PIX (cartão ainda deve montar); 'redirect' = sair */
            const tryResumePixReturn = async () => {
                const url = new URL(window.location.href);
                if (url.searchParams.get('stripe_pix_resume') !== '1') return false;

                const subId = sessionStorage.getItem('stripe_pix_sub_id');
                const raw = sessionStorage.getItem('stripe_pix_form');
                if (!subId || !raw) {
                    showError('Não foi possível retomar o cadastro PIX. Preencha o formulário e inicie de novo.');
                    url.searchParams.delete('stripe_pix_resume');
                    url.searchParams.delete('payment_intent');
                    url.searchParams.delete('payment_intent_client_secret');
                    url.searchParams.delete('redirect_status');
                    window.history.replaceState({}, '', url.pathname + (url.search || '') + url.hash);
                    return true;
                }

                let fields;
                try {
                    fields = JSON.parse(raw);
                } catch (_) {
                    showError('Dados do cadastro PIX inválidos. Tente novamente.');
                    return true;
                }

                const fd = new FormData();
                Object.keys(fields).forEach((k) => fd.append(k, fields[k]));
                fd.set('stripe_subscription_id', subId);
                fd.set('payment_option', 'pix');

                const btn = document.getElementById('signup-submit');
                if (btn) btn.disabled = true;

                try {
                    const fin = await fetch('/painel/cadastro/confirmar', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        body: fd,
                    });
                    let finJson = {};
                    try {
                        finJson = await fin.json();
                    } catch (_) {}

                    if (fin.ok && finJson.ok && finJson.redirect) {
                        sessionStorage.removeItem('stripe_pix_sub_id');
                        sessionStorage.removeItem('stripe_pix_form');
                        url.searchParams.delete('stripe_pix_resume');
                        url.searchParams.delete('payment_intent');
                        url.searchParams.delete('payment_intent_client_secret');
                        url.searchParams.delete('redirect_status');
                        window.history.replaceState({}, '', url.pathname + (url.search || '') + url.hash);
                        window.location.href = finJson.redirect;
                        return 'redirect';
                    }

                    showError(finJson.error || 'Assinatura ainda não está ativa. Aguarde a confirmação do PIX e atualize a página.');
                } catch (_) {
                    showError('Erro de rede ao concluir cadastro.');
                } finally {
                    if (btn) btn.disabled = false;
                }
                return true;
            };

            (async () => {
                const resume = await tryResumePixReturn();
                if (resume === 'redirect') return;

                const elements = stripe.elements();
                const card = elements.create('card', {
                    hidePostalCode: true,
                });
                card.mount('#stripe-card-element');

                card.on('change', (event) => {
                    if (event.error) {
                        showError(event.error.message || 'Dados de cartão inválidos.');
                        return;
                    }
                    errorEl.classList.add('d-none');
                });

                const cardSection = document.getElementById('stripe-card-section');
                const pixHint = document.getElementById('stripe-pix-hint');
                const payCard = document.getElementById('stripe-pay-card');
                const payPix = document.getElementById('stripe-pay-pix');

                const updatePayMethodUi = () => {
                    syncPaymentOptionFromUi();
                    const pix = isPixSelected();
                    if (cardSection) cardSection.classList.toggle('d-none', pix);
                    if (pixHint) pixHint.classList.toggle('d-none', !pix);
                    errorEl.classList.add('d-none');
                };

                if (stripePixOffered && payCard && payPix) {
                    payCard.addEventListener('change', updatePayMethodUi);
                    payPix.addEventListener('change', updatePayMethodUi);
                    updatePayMethodUi();
                }

                const finalizeAfterPrepare = async (fd, prepJson, btn) => {
                    const subId = prepJson.subscriptionId;
                    if (!subId) {
                        showError('Resposta inválida do servidor.');
                        if (btn) btn.disabled = false;
                        return;
                    }
                    subIdInput.value = subId;
                    fd.set('stripe_subscription_id', subId);

                    const fin = await fetch('/painel/cadastro/confirmar', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        body: fd,
                    });
                    let finJson = {};
                    try {
                        finJson = await fin.json();
                    } catch (_) {}

                    if (!fin.ok || !finJson.ok || !finJson.redirect) {
                        showError(finJson.error || 'Não foi possível finalizar o cadastro.');
                        if (btn) btn.disabled = false;
                        return;
                    }

                    window.location.href = finJson.redirect;
                };

                const paidFlow = async () => {
                    errorEl.classList.add('d-none');
                    syncPaymentOptionFromUi();
                    const btn = document.getElementById('signup-submit');
                    if (btn) btn.disabled = true;

                    try {
                        if (isPixSelected()) {
                            tokenInput.value = '';
                            methodInput.value = 'pix';
                            last4Input.value = '0000';

                            const fd = new FormData(form);
                            fd.set('payment_option', 'pix');
                            persistFormForPixResume(fd);

                            const prep = await fetch('/painel/cadastro/pagamento', {
                                method: 'POST',
                                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                                body: fd,
                            });
                            let prepJson = {};
                            try {
                                prepJson = await prep.json();
                            } catch (_) {}

                            if (!prep.ok) {
                                showError(prepJson.error || 'Falha ao iniciar pagamento PIX.');
                                if (btn) btn.disabled = false;
                                return;
                            }

                            if (!prepJson.clientSecret) {
                                await finalizeAfterPrepare(fd, prepJson, btn);
                                return;
                            }

                            sessionStorage.setItem('stripe_pix_sub_id', prepJson.subscriptionId);
                            const returnUrl = new URL(window.location.href);
                            returnUrl.searchParams.set('stripe_pix_resume', '1');

                            const pixRes = await stripe.confirmPixPayment(prepJson.clientSecret, {
                                return_url: returnUrl.toString(),
                            });

                            if (pixRes.error) {
                                showError(pixRes.error.message || 'PIX não autorizado.');
                                if (btn) btn.disabled = false;
                                return;
                            }

                            if (
                                pixRes.paymentIntent &&
                                ['succeeded', 'processing'].includes(pixRes.paymentIntent.status)
                            ) {
                                await finalizeAfterPrepare(fd, prepJson, btn);
                                return;
                            }

                            if (btn) btn.disabled = false;
                            return;
                        }

                        const billingName = (form.querySelector('input[name="name"]')?.value || '').trim();
                        const billingEmail = (emailInput?.value || '').trim();

                        const pmResult = await stripe.createPaymentMethod({
                            type: 'card',
                            card,
                            billing_details: {
                                name: billingName,
                                email: billingEmail,
                            },
                        });

                        if (pmResult.error || !pmResult.paymentMethod) {
                            showError(pmResult.error?.message || 'Não foi possível validar o cartão no Stripe.');
                            if (btn) btn.disabled = false;
                            return;
                        }

                        tokenInput.value = pmResult.paymentMethod.id;
                        methodInput.value = pmResult.paymentMethod.card?.brand || 'stripe';
                        last4Input.value = pmResult.paymentMethod.card?.last4 || '0000';

                        const fd = new FormData(form);
                        fd.set('payment_option', 'card');

                        const prep = await fetch('/painel/cadastro/pagamento', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            body: fd,
                        });
                        let prepJson = {};
                        try {
                            prepJson = await prep.json();
                        } catch (_) {}

                        if (!prep.ok) {
                            showError(prepJson.error || 'Falha ao iniciar pagamento.');
                            if (btn) btn.disabled = false;
                            return;
                        }

                        if (prepJson.clientSecret) {
                            const pay = await stripe.confirmCardPayment(prepJson.clientSecret);
                            if (pay.error) {
                                showError(pay.error.message || 'Pagamento não autorizado.');
                                if (btn) btn.disabled = false;
                                return;
                            }
                        }

                        await finalizeAfterPrepare(fd, prepJson, btn);
                    } catch (e) {
                        showError('Erro de rede. Tente novamente.');
                        if (btn) btn.disabled = false;
                    }
                };

                form.addEventListener('submit', async (event) => {
                    if (useStripePaidFlow) {
                        event.preventDefault();
                        await paidFlow();
                        return;
                    }

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
                        showError(result.error?.message || 'Não foi possível validar o cartão no Stripe.');
                        return;
                    }

                    tokenInput.value = result.paymentMethod.id;
                    methodInput.value = result.paymentMethod.card?.brand || 'stripe';
                    last4Input.value = result.paymentMethod.card?.last4 || '0000';
                    form.submit();
                });
            })();
        })();
    </script>
    <?php endif; ?>
</body>
</html>
