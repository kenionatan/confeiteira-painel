<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="col-lg-8 mx-auto">
    <div class="card">
        <div class="card-body">
            <h3 class="card-title"><?= esc($title ?? 'Cupom fiscal') ?></h3>
            <p class="text-secondary mb-3">Envie uma foto ou PDF do cupom e/ou cole o texto. Os itens serao sugeridos para revisao.</p>
            <?php
            $prov = strtolower((string) ($iaProvider ?? 'auto'));
            ?>
            <?php if (! empty($iaCupomConfigurada)): ?>
                <div class="alert alert-success mb-3 d-flex flex-column gap-2 py-3" role="status">
                    <div class="fw-semibold">Leitura por IA ativa</div>
                    <div class="small text-muted">Provedor: <code class="text-body"><?= esc($prov === '' ? 'auto' : $prov) ?></code></div>
                    <?php if (! empty($iaOcrSpaceConfigurada)): ?>
                        <div class="small"><strong>OCR.space</strong> configurado (OCR via API, rapido para cupom de imagem).</div>
                    <?php endif; ?>
                    <?php if (! empty($iaOllamaConfigurada)): ?>
                        <div class="small"><strong>Ollama</strong> em <code>cupomfiscal.ollamaBaseUrl</code> (ex.: <code>ollama pull <?= esc($cupomOllamaModel ?? 'llama3.2-vision') ?></code>). Imagens JPG/PNG/WEBP; PDF com Ollama use texto manual ou Gemini.</div>
                    <?php endif; ?>
                    <?php if (! empty($iaGeminiConfigurada)): ?>
                        <div class="small"><strong>Gemini</strong> para imagem/PDF na nuvem (<a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">chave</a>).</div>
                    <?php endif; ?>
                    <?php if ($prov === 'auto'): ?>
                        <div class="small text-muted border-top pt-2 mt-1">Modo <code>auto</code>: tenta OCR.space; se falhar, tenta Ollama; por fim Gemini (se configurado).</div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-3 d-flex flex-column gap-2 py-3">
                    <div class="fw-semibold">Leitura automatica desligada</div>
                    <p class="small mb-0">Configure no <code>.env</code> pelo menos um:</p>
                    <ul class="small mb-0 mt-0 ps-3">
                        <li><strong>OCR.space</strong>: <code>cupomfiscal.ocrSpaceApiKey</code> (recomendado para cupom em imagem).</li>
                        <li><strong>Ollama</strong> (gratis no seu servidor): <code>cupomfiscal.ollamaBaseUrl = http://127.0.0.1:11434</code> e <code>cupomfiscal.ollamaModel = llama3.2-vision</code> (rode <code>ollama pull llama3.2-vision</code>).</li>
                        <li><strong>Gemini</strong>: <code>cupomfiscal.geminiApiKey</code> — <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>.</li>
                    </ul>
                    <div class="small"><code>cupomfiscal.iaProvider</code> = <code>auto</code> | <code>ocrspace</code> | <code>ollama</code> | <code>gemini</code></div>
                </div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger">
                    <?php foreach ((array) session()->getFlashdata('errors') as $err): ?>
                        <div><?= esc(is_array($err) ? implode(' ', $err) : $err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= base_url('cupom-fiscal/enviar') ?>" enctype="multipart/form-data" id="form-cupom-fiscal" data-enviar-stream="<?= esc(base_url('cupom-fiscal/enviar-stream')) ?>" data-fallback-url="<?= esc(base_url('cupom-fiscal')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Arquivo (opcional)</label>
                    <input type="file" name="arquivo" id="cupom-arquivo" class="visually-hidden" accept=".txt,.pdf,.jpg,.jpeg,.png,.webp">
                    <div class="cupom-dropzone rounded-3 position-relative" id="cupom-dropzone" role="presentation">
                        <label for="cupom-arquivo" class="cupom-dropzone-inner d-flex flex-column align-items-center justify-content-center gap-2 text-center mb-0 cursor-pointer rounded-3 px-3 py-4 w-100">
                            <span class="text-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg" width="48" height="48" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 18a4.6 4.4 0 0 1 0 -9a5 4.5 0 0 1 11 2h1a3.5 3.5 0 0 1 0 7h-1" /><path d="M9 15l3 -3l3 3" /><path d="M12 12v9" /></svg>
                            </span>
                            <span class="fw-medium text-body">Arraste a foto ou o PDF aqui</span>
                            <span class="small text-secondary">ou clique para escolher · JPG, PNG, WEBP, PDF, TXT</span>
                            <span class="small text-primary fw-medium d-none" id="cupom-arquivo-nome"></span>
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Texto do cupom (recomendado)</label>
                    <textarea class="form-control" name="texto_cupom" id="cupom-texto" rows="12" placeholder="Cole aqui as linhas de itens do cupom..."><?= esc(old('texto_cupom')) ?></textarea>
                </div>
                <button class="btn btn-primary" type="submit" id="btn-cupom-enviar">Continuar para revisao</button>
                <a href="<?= base_url('produtos') ?>" class="btn btn-link">Voltar aos produtos</a>
            </form>
        </div>
    </div>
</div>

<div class="modal modal-blur fade" id="modal-cupom-processando" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Processando cupom</h5>
            </div>
            <div class="modal-body">
                <p class="text-secondary small mb-2">Aguarde. Tempo estimado: ate ~30 s (pode variar conforme servidor/modelo).</p>
                <div class="progress mb-1" style="height: 10px;" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="cupom-progress-wrap">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="cupom-progress-bar" style="width: 0%;"></div>
                </div>
                <p class="text-secondary small mb-3" id="cupom-progress-pct">0%</p>
                <label class="form-label text-secondary small">Log</label>
                <pre class="cupom-log-box bg-dark text-light p-3 rounded small mb-0" id="cupom-log-area" style="max-height: 220px; overflow: auto; white-space: pre-wrap; word-break: break-word;"></pre>
                <div class="alert alert-danger mt-3 d-none" id="cupom-stream-erro"></div>
            </div>
            <div class="modal-footer d-none" id="cupom-modal-footer-erro">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="btn-cupom-fechar-erro">Fechar e tentar de novo</button>
            </div>
        </div>
    </div>
</div>

<style>
    .cupom-dropzone {
        border: 2px dashed var(--tblr-border-color, rgba(4, 32, 69, 0.14));
        background: var(--tblr-bg-surface-secondary, rgba(32, 107, 196, 0.04));
        transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
    }
    .cupom-dropzone.cupom-dropzone--active {
        border-color: var(--tblr-primary, #206bc4);
        background: rgba(32, 107, 196, 0.08);
        box-shadow: inset 0 0 0 1px rgba(32, 107, 196, 0.2);
    }
    .cupom-dropzone-inner {
        min-height: 8rem;
    }
    [data-theme="dark"] .cupom-dropzone {
        border-color: rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.03);
    }
    [data-theme="dark"] .cupom-dropzone.cupom-dropzone--active {
        border-color: var(--tblr-primary);
        background: rgba(32, 107, 196, 0.12);
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(() => {
    const form = document.getElementById('form-cupom-fiscal');
    const btn = document.getElementById('btn-cupom-enviar');
    const logArea = document.getElementById('cupom-log-area');
    const errBox = document.getElementById('cupom-stream-erro');
    const errFooter = document.getElementById('cupom-modal-footer-erro');
    const modalEl = document.getElementById('modal-cupom-processando');
    const progressBar = document.getElementById('cupom-progress-bar');
    const progressWrap = document.getElementById('cupom-progress-wrap');
    const progressPct = document.getElementById('cupom-progress-pct');
    if (!form || !modalEl || !logArea || !errBox || !errFooter || !btn) return;

    const ESTIMATE_MS = 30000;
    let modalInstance = null;
    let openedWithFallback = false;
    let progressTimerId = null;

    function getModal() {
        const B = window.bootstrap;
        if (!B || !B.Modal) return null;
        if (!modalInstance) {
            modalInstance = typeof B.Modal.getOrCreateInstance === 'function'
                ? B.Modal.getOrCreateInstance(modalEl)
                : new B.Modal(modalEl);
        }
        return modalInstance;
    }

    function appendLog(line) {
        const t = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        logArea.textContent += '[' + t + '] ' + line + '\n';
        logArea.scrollTop = logArea.scrollHeight;
    }

    function resetModal() {
        stopProgressEstimate(0);
        logArea.textContent = '';
        errBox.classList.add('d-none');
        errBox.textContent = '';
        errFooter.classList.add('d-none');
        btn.disabled = false;
    }

    function showModalFallback() {
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        modalEl.setAttribute('aria-modal', 'true');
        let backdrop = document.getElementById('cupom-modal-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'cupom-modal-backdrop';
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    }

    function hideModalFallback() {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');
        document.getElementById('cupom-modal-backdrop')?.remove();
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
    }

    function setProgressDisplay(pct) {
        const p = Math.max(0, Math.min(100, pct));
        if (progressBar) progressBar.style.width = p + '%';
        if (progressWrap) progressWrap.setAttribute('aria-valuenow', String(p));
        if (progressPct) progressPct.textContent = p + '%';
    }

    function startProgressEstimate() {
        stopProgressEstimate();
        const t0 = Date.now();
        progressTimerId = setInterval(() => {
            const elapsed = Date.now() - t0;
            const pct = Math.min(95, Math.floor((elapsed / ESTIMATE_MS) * 95));
            setProgressDisplay(pct);
        }, 120);
    }

    function stopProgressEstimate(finalPct) {
        if (progressTimerId !== null) {
            clearInterval(progressTimerId);
            progressTimerId = null;
        }
        if (typeof finalPct === 'number') {
            setProgressDisplay(finalPct);
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        resetModal();
        btn.disabled = true;
        openedWithFallback = false;
        startProgressEstimate();
        const modal = getModal();
        if (modal) {
            modal.show();
        } else {
            openedWithFallback = true;
            appendLog('Aviso: Bootstrap Modal indisponivel; usando painel fixo.');
            showModalFallback();
        }
        appendLog('Iniciando envio (stream)...');

        const fd = new FormData(form);

        try {
            const streamUrl = form.getAttribute('data-enviar-stream') || '/cupom-fiscal/enviar-stream';
            const res = await fetch(streamUrl, {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/x-ndjson', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!res.ok || !res.body) {
                appendLog('Erro HTTP ' + res.status);
                errBox.textContent = 'Falha na conexao com o servidor.';
                errBox.classList.remove('d-none');
                errFooter.classList.remove('d-none');
                return;
            }

            const reader = res.body.getReader();
            const dec = new TextDecoder();
            let buf = '';

            function handleEvent(ev) {
                if (ev.t === 'log' && ev.m) {
                    appendLog(ev.m);
                } else if (ev.t === 'error' && ev.errors) {
                    const msg = Array.isArray(ev.errors) ? ev.errors.join(' ') : String(ev.errors);
                    appendLog('ERRO: ' + msg);
                    errBox.textContent = msg;
                    errBox.classList.remove('d-none');
                    errFooter.classList.remove('d-none');
                } else if (ev.t === 'done') {
                    stopProgressEstimate(100);
                    appendLog('Concluido. Redirecionando...');
                    if (ev.debug) {
                        const d = ev.debug;
                        if (typeof d.linhas_count === 'number') {
                            appendLog('Resumo: ' + d.linhas_count + ' linha(s) candidata(s), texto bruto ' + (d.extra_texto_len || 0) + ' caracteres.');
                        }
                        if (d.ia_provedor) {
                            appendLog('Provedor IA: ' + d.ia_provedor);
                        }
                        if (d.preview_nomes && d.preview_nomes.length) {
                            appendLog('Primeiros nomes: ' + d.preview_nomes.slice(0, 5).join(' | '));
                        }
                        if (d.trecho_texto) {
                            appendLog('Trecho do texto bruto: ' + String(d.trecho_texto).slice(0, 200).replace(/\s+/g, ' ') + '...');
                        }
                    }
                    if (ev.warning) {
                        sessionStorage.setItem('cupom_fiscal_warning', ev.warning);
                    }
                    setTimeout(() => {
                        const fb = form.getAttribute('data-fallback-url') || '/cupom-fiscal';
                        window.location.href = ev.redirect || fb;
                    }, ev.warning ? 1200 : 600);
                }
            }

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                buf += dec.decode(value, { stream: true });
                const parts = buf.split('\n');
                buf = parts.pop() || '';
                for (const line of parts) {
                    const s = line.trim();
                    if (!s) continue;
                    try {
                        handleEvent(JSON.parse(s));
                    } catch (x) {
                        appendLog('(linha JSON invalida)');
                    }
                }
            }
            const tail = buf.trim();
            if (tail) {
                try {
                    handleEvent(JSON.parse(tail));
                } catch (x) { /* ignore */ }
            }
        } catch (err) {
            stopProgressEstimate();
            appendLog('Excecao: ' + (err && err.message ? err.message : String(err)));
            errBox.textContent = 'Falha no navegador ao processar a resposta.';
            errBox.classList.remove('d-none');
            errFooter.classList.remove('d-none');
        }
    });

    document.getElementById('btn-cupom-fechar-erro')?.addEventListener('click', () => {
        stopProgressEstimate();
        btn.disabled = false;
        if (openedWithFallback) {
            hideModalFallback();
        } else {
            getModal()?.hide();
        }
    });

    const dz = document.getElementById('cupom-dropzone');
    const fileInp = document.getElementById('cupom-arquivo');
    const fileNameEl = document.getElementById('cupom-arquivo-nome');
    if (dz && fileInp) {
        function syncFileLabel() {
            if (fileNameEl && fileInp.files && fileInp.files.length) {
                fileNameEl.textContent = fileInp.files[0].name;
                fileNameEl.classList.remove('d-none');
            } else if (fileNameEl) {
                fileNameEl.textContent = '';
                fileNameEl.classList.add('d-none');
            }
        }
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((evName) => {
            dz.addEventListener(evName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        dz.addEventListener('dragenter', () => dz.classList.add('cupom-dropzone--active'));
        dz.addEventListener('dragover', () => dz.classList.add('cupom-dropzone--active'));
        dz.addEventListener('dragleave', (e) => {
            if (e.relatedTarget && dz.contains(e.relatedTarget)) {
                return;
            }
            dz.classList.remove('cupom-dropzone--active');
        });
        dz.addEventListener('drop', (e) => {
            dz.classList.remove('cupom-dropzone--active');
            const files = e.dataTransfer.files;
            if (!files.length) {
                return;
            }
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInp.files = dt.files;
            syncFileLabel();
        });
        fileInp.addEventListener('change', syncFileLabel);
    }
})();
</script>
<?= $this->endSection() ?>
