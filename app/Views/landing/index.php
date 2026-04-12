<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Confeiteira Pro') ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.3.2/dist/css/tabler.min.css">
    <script>document.documentElement.classList.add('js');</script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pacifico&display=swap');

        :root {
            --brand: #8b5cf6;
            --brand-dark: #6d28d9;
            --accent: #22d3ee;
            --bg: #020617;
            --panel: #0b1222;
            --text: #e2e8f0;
            --text-soft: #94a3b8;
        }

        * {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, sans-serif;
            background:
                radial-gradient(circle at 5% 10%, rgba(139, 92, 246, 0.2), transparent 45%),
                radial-gradient(circle at 90% 15%, rgba(34, 211, 238, 0.12), transparent 30%),
                linear-gradient(180deg, #020617 0%, #0b1324 45%, #020617 100%);
            color: var(--text);
            overflow-x: hidden;
        }

        .floating-glow {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }

        .floating-glow::before,
        .floating-glow::after {
            content: '';
            position: absolute;
            width: 32rem;
            height: 32rem;
            border-radius: 999px;
            filter: blur(80px);
            opacity: 0.2;
            animation: drift 18s ease-in-out infinite alternate;
        }

        .floating-glow::before {
            background: #8b5cf6;
            top: -12rem;
            left: -8rem;
        }

        .floating-glow::after {
            background: #06b6d4;
            right: -6rem;
            top: 12rem;
            animation-delay: 2s;
        }

        @keyframes drift {
            from { transform: translateY(0) translateX(0) scale(1); }
            to { transform: translateY(2rem) translateX(-2rem) scale(1.05); }
        }

        main {
            position: relative;
            z-index: 1;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(2, 6, 23, 0.75);
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            backdrop-filter: blur(8px);
        }

        .brand-logo {
            font-family: 'Pacifico', cursive;
            font-size: 1.9rem;
            line-height: 1;
            margin: 0;
            letter-spacing: 0.4px;
            background: linear-gradient(90deg, #c4b5fd 0%, #67e8f9 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            height: 44px;
        }

        .hero {
            min-height: calc(96vh - 72px);
            position: relative;
            display: flex;
            align-items: center;
        }

        .hero h1 {
            font-size: clamp(2.1rem, 5vw, 4.2rem);
            line-height: 1.05;
            letter-spacing: -0.02em;
            color: #fff;
        }

        .hero p {
            color: #cbd5e1;
            font-size: 1.08rem;
            max-width: 58ch;
        }

        .highlight {
            background: linear-gradient(90deg, #c4b5fd 0%, #67e8f9 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .badge-soft {
            background: rgba(139, 92, 246, 0.16);
            color: #ddd6fe;
            border: 1px solid rgba(139, 92, 246, 0.45);
            font-weight: 600;
        }

        .btn-brand {
            background: linear-gradient(120deg, var(--brand) 0%, var(--brand-dark) 100%);
            border-color: transparent;
            color: #fff;
            font-weight: 600;
            box-shadow: 0 14px 26px rgba(109, 40, 217, 0.35);
        }

        .btn-brand:hover {
            color: #fff;
            transform: translateY(-1px);
        }

        .hero-panel {
            background: linear-gradient(165deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.78) 100%);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 18px;
            backdrop-filter: blur(7px);
            box-shadow: 0 18px 45px rgba(2, 6, 23, 0.5);
        }

        .popular-card-wrap {
            position: relative;
        }

        .popular-card {
            position: relative;
            overflow: visible;
        }

        .popular-card-wrap .chart-corner {
            position: absolute;
            top: -4.5rem;
            right: 0.9rem;
            width: 96px;
            opacity: 0.98;
            z-index: 3;
            pointer-events: none;
        }

        .badge-popular {
            background: rgba(139, 92, 246, 0.22);
            color: #e9d5ff;
            border: 1px solid rgba(167, 139, 250, 0.45);
            margin-right: 0.35rem;
        }

        .badge-offer {
            background: rgba(34, 211, 238, 0.15);
            color: #a5f3fc;
            border: 1px solid rgba(34, 211, 238, 0.35);
        }

        .metric {
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 14px;
            background: rgba(15, 23, 42, 0.72);
            text-align: center;
            padding: 1rem;
        }

        .metric strong {
            display: block;
            color: #fff;
            font-size: 1.65rem;
            line-height: 1;
        }

        .metric span {
            color: var(--text-soft);
            font-size: 0.85rem;
        }

        .timer-box {
            background: rgba(139, 92, 246, 0.2);
            border: 1px solid rgba(167, 139, 250, 0.42);
            border-radius: 12px;
            padding: 0.7rem 1rem;
            display: inline-block;
            font-size: 0.95rem;
        }

        .section-title {
            color: #fff;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            letter-spacing: -0.01em;
            margin-bottom: 0.65rem;
        }

        .section-subtitle {
            color: var(--text-soft);
            max-width: 68ch;
            margin: 0 auto;
        }

        .benefit-card,
        .plan-card,
        .testimonial-card {
            background: var(--panel);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 16px;
            transition: transform 0.24s ease, border-color 0.24s ease, box-shadow 0.24s ease;
            height: 100%;
        }

        .benefit-card {
            padding: 1.2rem;
        }

        .benefit-card:hover {
            transform: translateY(-3px);
            border-color: rgba(125, 211, 252, 0.4);
            box-shadow: 0 18px 35px rgba(2, 6, 23, 0.45);
        }

        .benefit-icon {
            width: 2.2rem;
            height: 2.2rem;
            border-radius: 0.7rem;
            display: grid;
            place-items: center;
            background: rgba(99, 102, 241, 0.25);
            color: #c4b5fd;
            margin-bottom: 0.9rem;
            font-weight: 700;
        }

        .plan-card {
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .plan-card::before {
            content: '';
            position: absolute;
            inset: auto -35% -55% -35%;
            height: 220px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.24) 0%, transparent 70%);
            z-index: 0;
            pointer-events: none;
        }

        .plan-card > * {
            position: relative;
            z-index: 1;
        }

        .plan-card.pro {
            border-color: rgba(167, 139, 250, 0.75);
            box-shadow: 0 0 0 1px rgba(167, 139, 250, 0.2), 0 20px 44px rgba(124, 58, 237, 0.25);
        }

        .plan-old {
            text-decoration: line-through;
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .plan-price {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin: 0.2rem 0;
        }

        .feature-list {
            padding-left: 1rem;
            color: #cbd5e1;
        }

        .site-footer {
            border-top: 1px solid rgba(148, 163, 184, 0.18);
            color: var(--text-soft);
            text-align: center;
            padding: 1rem 0 1.4rem;
            margin-top: 1rem;
        }

        .testimonial-wrap {
            position: relative;
            overflow: hidden;
        }

        .testimonial-track {
            display: flex;
            transition: transform 0.5s ease;
        }

        .testimonial-card {
            min-width: 100%;
            padding: 1.4rem;
        }

        .revealed {
            opacity: 1;
            transform: translateY(0);
        }

        .reveal {
            opacity: 1;
            transform: none;
            transition: opacity 0.45s ease, transform 0.45s ease;
        }

        .js .reveal {
            opacity: 0;
            transform: translateY(22px);
            transition: opacity 0.45s ease, transform 0.45s ease;
        }

        .js .reveal.revealed {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-16541205225"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'AW-16541205225');
    </script>
</head>
<body>
    <div class="floating-glow"></div>
    <main>
        <header class="site-header">
            <div class="container-xl py-2 d-flex align-items-center">
                <h1 class="brand-logo">appdoce</h1>
            </div>
        </header>

        <section class="hero">
            <div class="container-xl py-6">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge badge-soft mb-3">Sistema premium para confeitaria</span>
                        <h1>Transforme sua confeitaria em uma <span class="highlight">máquina de vendas organizada</span>.</h1>
                        <p class="mt-3 mb-4">
                            Comece no plano Free com cadastro completo da conta e evolua para os planos pagos no seu ritmo.
                            Controle clientes, pedidos e assinaturas em uma experiência moderna e rápida.
                        </p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-brand btn-lg" href="#planos">Ver planos</a>
                        </div>
                        <div class="mt-4 timer-box">
                            Oferta por tempo limitado termina em: <strong id="offer-countdown">--:--:--</strong>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="popular-card-wrap">
                            <img class="chart-corner" src="/images/chartchar.png" alt="Gráfico do plano Pro">
                            <div class="hero-panel popular-card p-3 p-md-4 tilt-card">
                                <div class="mb-2">
                                    <!--<span class="badge badge-popular">Popular</span>-->
                                    <!--<span class="badge badge-offer">Oferta por tempo limitado</span>-->
                                </div>
                                <h3 class="text-white mb-1">Gátis</h3>
                                <p class="text-secondary mb-3">Tenha mais controle sobre sua confeitaria.</p>
                                <!--<div class="plan-old">R$ 79,90/mês</div>-->
                                <div class="plan-price mb-1">R$ 0,00</div>
                                <ul class="feature-list mb-4">
                                    <li>Precificação</li>
                                    <li>Cadastro de produtos</li>
                                    <li>Mais</li>
                                </ul>
                                <a class="btn btn-brand w-100" href="/assinar/free">Comece grátis</a>
                                <p class="text-secondary mb-0">
                                    Cadastro rápido e fácil.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container-xl">
                <div class="text-center mb-4">
                    <h2 class="section-title">Feito para acelerar seu dia</h2>
                    <p class="section-subtitle">
                        Inspirado no estilo de landing pages SaaS modernas: foco em conversão, velocidade e clareza da proposta.
                    </p>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="benefit-card reveal">
                            <div class="benefit-icon">01</div>
                            <h4 class="text-white">Gestão centralizada</h4>
                            <p class="text-secondary mb-0">Clientes, pedidos e financeiro em uma única visão com menos retrabalho.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benefit-card reveal">
                            <div class="benefit-icon">02</div>
                            <h4 class="text-white">Checkout otimizado</h4>
                            <p class="text-secondary mb-0">Fluxo rápido para assinatura e renovação com cartão.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benefit-card reveal">
                            <div class="benefit-icon">03</div>
                            <h4 class="text-white">Escala sem caos</h4>
                            <p class="text-secondary mb-0">Automatize rotinas e tome decisoes com dados em tempo real.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="planos" class="py-5">
            <div class="container-xl">
                <div class="text-center mb-4 reveal">
                    <h2 class="section-title">Escolha seu plano</h2>
                    <p class="section-subtitle">Comece no Básico e evolua para o Pro com funcionalidades de IA.</p>
                </div>
                <div class="row g-4">
                    <?php foreach (($plans ?? []) as $slug => $plan): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="plan-card tilt-card <?= ! empty($plan['destaque']) ? 'pro' : '' ?>">
                                <?php if (! empty($plan['destaque'])): ?>
                                    <div class="mb-2">
                                        <span class="badge badge-popular">Popular</span>
                                        <span class="badge badge-offer">Oferta por tempo limitado</span>
                                    </div>
                                <?php endif; ?>
                                <h3 class="text-white mb-1"><?= esc($plan['nome']) ?></h3>
                                <p class="text-secondary mb-2"><?= esc($plan['descricao']) ?></p>
                                <?php if (! empty($plan['valorAnterior'])): ?>
                                    <div class="plan-old"><?= esc($plan['valorAnterior']) ?>/mês</div>
                                <?php endif; ?>
                                <div class="plan-price"><?= esc($plan['valor']) ?><span class="fs-5 text-secondary">/mês</span></div>
                                <ul class="feature-list mb-4">
                                    <?php foreach (($plan['features'] ?? []) as $feature): ?>
                                        <li><?= esc($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <a class="btn btn-brand w-100" href="/assinar/<?= esc($slug) ?>">
                                    <?= $slug === 'free' ? 'Cadastrar grátis' : 'Assinar ' . esc($plan['nome']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="pb-6">
            <div class="container-xl">
                <div class="text-center mb-4 reveal">
                    <h2 class="section-title">Quem usa recomenda</h2>
                    <p class="section-subtitle">Resultados reais de quem já organizou a operação com o sistema.</p>
                </div>
                <div class="testimonial-wrap reveal">
                    <div class="testimonial-track" id="testimonial-track">
                        <article class="testimonial-card">
                            <p class="mb-2 text-secondary">"Saimos de planilhas para um processo claro. Hoje sabemos exatamente o que vender e quando produzir."</p>
                            <strong class="text-white">Carla, doceria artesanal</strong>
                        </article>
                        <article class="testimonial-card">
                            <p class="mb-2 text-secondary">"O checkout de assinatura reduziu muito nosso trabalho manual e o caixa ficou previsivel."</p>
                            <strong class="text-white">Rafaela, confeitaria premium</strong>
                        </article>
                        <article class="testimonial-card">
                            <p class="mb-2 text-secondary">"A equipe ganhou velocidade no atendimento, e o cliente percebeu a melhora na hora."</p>
                            <strong class="text-white">Juliana, ateliê de bolos</strong>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <footer class="site-footer">
            2026 - appdoce.top.
        </footer>
    </main>

    <script>
        (() => {
            // Countdown da oferta
            const output = document.getElementById('offer-countdown');
            const targetDate = new Date('<?= esc($offerEndsAt ?? '') ?>').getTime();
            if (output && !Number.isNaN(targetDate)) {
                const render = () => {
                    const now = Date.now();
                    const diff = targetDate - now;
                    if (diff <= 0) {
                        output.textContent = 'Oferta encerrada';
                        return;
                    }
                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
                    const mins = Math.floor((diff / (1000 * 60)) % 60);
                    const secs = Math.floor((diff / 1000) % 60);
                    const totalHours = Math.floor(diff / (1000 * 60 * 60));
                    if (days > 0) {
                        output.textContent =
                            `${String(days).padStart(2, '0')}d ${String(hours).padStart(2, '0')}h ${String(mins).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s`;
                    } else {
                        output.textContent =
                            `${String(totalHours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
                    }
                };
                render();
                setInterval(render, 1000);
            }

            // Animação de contadores
            const counters = document.querySelectorAll('.counter');
            const animateCounter = (entry) => {
                const el = entry.target;
                const target = Number(el.dataset.target || 0);
                const duration = 1400;
                const start = performance.now();
                const from = 0;

                const tick = (now) => {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const value = Math.floor(from + (target - from) * eased);
                    el.textContent = new Intl.NumberFormat('pt-BR').format(value);
                    if (progress < 1) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
            };

            const counterObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    animateCounter(entry);
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.45 });

            counters.forEach((counter) => counterObserver.observe(counter));

            // Reveal no scroll
            const revealObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.2 });

            document.querySelectorAll('.reveal').forEach((el) => revealObserver.observe(el));

            // Carrossel simples de depoimentos
            const track = document.getElementById('testimonial-track');
            if (track) {
                let index = 0;
                const total = track.children.length;
                setInterval(() => {
                    index = (index + 1) % total;
                    track.style.transform = `translateX(-${index * 100}%)`;
                }, 3800);
            }

            // Leve efeito 3D nos cards para chamar atenção
            const tilts = document.querySelectorAll('.tilt-card');
            tilts.forEach((card) => {
                card.addEventListener('mousemove', (event) => {
                    const rect = card.getBoundingClientRect();
                    const x = event.clientX - rect.left;
                    const y = event.clientY - rect.top;
                    const midX = rect.width / 2;
                    const midY = rect.height / 2;
                    const rotateY = ((x - midX) / midX) * 4;
                    const rotateX = ((midY - y) / midY) * 4;
                    card.style.transform = `perspective(900px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = '';
                });
            });
        })();
    </script>
</body>
</html>
