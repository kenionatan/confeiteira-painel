<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.3.2/dist/css/tabler.min.css">
    <style>
        .domain-addon { min-width: 160px; }
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
                    <?php if (empty($mercadoPagoPublicKey)): ?>
                        <div class="alert alert-warning">
                            Configure a chave publica do Mercado Pago para habilitar o cadastro com cartao.
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

                        <hr class="my-4">
                        <h3 class="h4 mb-3">Cartao para validacao do plano Free</h3>
                        <p class="text-secondary mb-3">Nenhuma cobranca sera feita agora. O cadastro do cartao e obrigatorio para concluir.</p>

                        <div class="mb-3">
                            <label class="form-label">Numero do cartao</label>
                            <input type="text" id="form-checkout__cardNumber" class="form-control" autocomplete="off" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label">Mes</label>
                                <input type="text" id="form-checkout__cardExpirationMonth" class="form-control" placeholder="MM" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Ano</label>
                                <input type="text" id="form-checkout__cardExpirationYear" class="form-control" placeholder="AA" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">CVV</label>
                                <input type="text" id="form-checkout__securityCode" class="form-control" required>
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

                        <input type="hidden" name="mp_card_token" id="mp_card_token" value="<?= esc(old('mp_card_token')) ?>">
                        <input type="hidden" name="mp_payment_method_id" id="mp_payment_method_id" value="<?= esc(old('mp_payment_method_id')) ?>">
                        <input type="hidden" name="mp_last_four_digits" id="mp_last_four_digits" value="<?= esc(old('mp_last_four_digits')) ?>">
                        <div class="alert alert-danger mt-3 d-none" id="card-error"></div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100" <?= empty($mercadoPagoPublicKey) ? 'disabled' : '' ?>>Cadastrar</button>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <a href="/painel/login" class="btn btn-outline-secondary w-100">Voltar para login</a>
                </div>
            </div>
        </div>
    </div>
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

            const showError = (message) => {
                errorEl.classList.remove('d-none');
                errorEl.textContent = message;
            };

            const onlyDigits = (value) => (value || '').replace(/\D+/g, '');
            const normalizeYear = (value) => {
                const digits = onlyDigits(value);
                if (digits.length === 4) return digits.slice(2);
                return digits;
            };

            form.addEventListener('submit', async (event) => {
                if (tokenInput.value) return;
                event.preventDefault();
                errorEl.classList.add('d-none');

                const cardNumber = onlyDigits(document.getElementById('form-checkout__cardNumber').value);
                const cardExpirationMonth = onlyDigits(document.getElementById('form-checkout__cardExpirationMonth').value).padStart(2, '0');
                const cardExpirationYear = normalizeYear(document.getElementById('form-checkout__cardExpirationYear').value);
                const securityCode = onlyDigits(document.getElementById('form-checkout__securityCode').value);
                const cardholderName = document.getElementById('form-checkout__cardholderName').value.trim();
                const identificationType = document.getElementById('form-checkout__identificationType').value;
                const identificationNumber = onlyDigits(document.getElementById('form-checkout__identificationNumber').value);
                const email = form.querySelector('input[name="email"]').value.trim();

                if (cardExpirationYear.length !== 2) {
                    showError('Informe o ano com 2 digitos (ex.: 30) ou 4 digitos (ex.: 2030).');
                    return;
                }

                if (!cardNumber || cardNumber.length < 13) {
                    showError('Numero de cartao invalido.');
                    return;
                }

                try {
                    const tokenResponse = await mp.createCardToken({
                        cardNumber,
                        cardholderName,
                        cardExpirationMonth,
                        cardExpirationYear,
                        securityCode,
                        identificationType,
                        identificationNumber,
                    });

                    if (!tokenResponse || !tokenResponse.id) {
                        showError('Nao foi possivel validar o cartao.');
                        return;
                    }

                    let paymentMethodId = '';
                    const bin = cardNumber.substring(0, 8);
                    try {
                        const methods = await mp.getPaymentMethods({ bin });
                        paymentMethodId = methods?.results?.[0]?.id || '';
                    } catch (e) {
                        paymentMethodId = '';
                    }

                    tokenInput.value = tokenResponse.id;
                    methodInput.value = paymentMethodId || 'desconhecido';
                    last4Input.value = tokenResponse.last_four_digits || cardNumber.slice(-4);

                    if (!email) {
                        showError('Informe um email valido para continuar.');
                        return;
                    }

                    form.submit();
                } catch (error) {
                    const mpMessage =
                        error?.message ||
                        error?.cause?.[0]?.description ||
                        error?.cause?.[0]?.message ||
                        'Cartao invalido ou nao autorizado para teste.';
                    showError(mpMessage);
                }
            });
        })();
    </script>
</body>
</html>

