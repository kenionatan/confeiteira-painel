<?php

namespace App\Controllers;

use App\Libraries\CupomFiscalGeminiExtractor;
use App\Libraries\CupomFiscalOcrSpaceExtractor;
use App\Libraries\CupomFiscalOllamaExtractor;
use App\Models\CategoriaProdutoModel;
use App\Models\CupomFiscalImportModel;
use App\Models\ProdutoModel;
use Config\CupomFiscal;

class CupomFiscalController extends BaseController
{
    public function index(): string
    {
        $cupomCfg = config('CupomFiscal');

        $ocrSpaceOk = $cupomCfg instanceof CupomFiscal && trim((string) $cupomCfg->ocrSpaceApiKey) !== '';
        $ollamaOk = $cupomCfg instanceof CupomFiscal && trim((string) $cupomCfg->ollamaBaseUrl) !== '';
        $geminiOk = $cupomCfg instanceof CupomFiscal && $cupomCfg->geminiApiKey !== '';

        return view('cupom_fiscal/index', [
            'title' => 'Importar cupom fiscal',
            'iaCupomConfigurada' => $ocrSpaceOk || $ollamaOk || $geminiOk,
            'iaOcrSpaceConfigurada' => $ocrSpaceOk,
            'iaOllamaConfigurada' => $ollamaOk,
            'iaGeminiConfigurada' => $geminiOk,
            'iaProvider' => $cupomCfg instanceof CupomFiscal ? strtolower(trim($cupomCfg->iaProvider)) : 'auto',
            'cupomOllamaModel' => $cupomCfg instanceof CupomFiscal ? $cupomCfg->ollamaModel : 'llama3.2-vision',
        ]);
    }

    public function enviar()
    {
        $result = $this->executarImportacaoCupom(null, true);
        if (! $result['success']) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        if ($result['flash_warning'] !== null) {
            session()->setFlashdata('warning', $result['flash_warning']);
        }

        return redirect()->to('/cupom-fiscal/revisar/' . (int) $result['import_id']);
    }

    /**
     * Upload com logs em tempo real (NDJSON, uma linha JSON por evento).
     * Eventos: { "t":"log", "m":"..." } | { "t":"done", "redirect":"...", "warning":null, "debug":{} } | { "t":"error", "errors":[] }
     */
    public function enviarStream()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setBody('Method Not Allowed');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/x-ndjson; charset=UTF-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');

        $emit = static function (string $type, array $payload = []): void {
            echo json_encode(array_merge(['t' => $type], $payload), JSON_UNESCAPED_UNICODE) . "\n";
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            flush();
        };

        $emit('log', ['m' => 'Validando formulario e arquivo...']);

        $onLog = static function (string $message) use ($emit): void {
            $emit('log', ['m' => $message]);
        };

        $result = $this->executarImportacaoCupom($onLog, false);

        if (! $result['success']) {
            $emit('error', ['errors' => $result['errors']]);
            exit;
        }

        $emit('done', [
            'redirect' => base_url('cupom-fiscal/revisar/' . (int) $result['import_id']),
            'warning' => $result['flash_warning'],
            'debug' => $result['debug'],
        ]);
        exit;
    }

    public function revisar(int $id): string
    {
        $model = new CupomFiscalImportModel();
        $import = $model->find($id);
        if (! $import) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $linhas = [];
        if (! empty($import['linhas_json'])) {
            $decoded = json_decode((string) $import['linhas_json'], true);
            $linhas = is_array($decoded) ? $decoded : [];
        }

        $linhasSemDadosSalvos = false;
        if ($linhas === []) {
            $linhas = [$this->linhaVazia()];
            $linhasSemDadosSalvos = true;
        }

        $catModel = new CategoriaProdutoModel();
        $prodModel = new ProdutoModel();

        return view('cupom_fiscal/revisar', [
            'title' => 'Revisar importação do cupom',
            'import' => $import,
            'linhas' => $linhas,
            'linhas_sem_dados_salvos' => $linhasSemDadosSalvos,
            'categorias' => $catModel->orderBy('nome', 'ASC')->findAll(),
            'produtos' => $prodModel->select('produtos.id, produtos.nome, categorias_produto.nome as categoria_nome')
                ->join('categorias_produto', 'categorias_produto.id = produtos.categoria_id', 'left')
                ->orderBy('produtos.nome', 'ASC')
                ->findAll(),
        ]);
    }

    public function salvarMassa(int $id)
    {
        $model = new CupomFiscalImportModel();
        $import = $model->find($id);
        if (! $import) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rows = (array) $this->request->getPost('linhas');
        $produtoModel = new ProdutoModel();
        $criados = 0;
        $ignorados = 0;

        foreach ($rows as $row) {
            $incluir = ! empty($row['incluir']);
            $assocId = isset($row['produto_associado_id']) ? (int) $row['produto_associado_id'] : 0;
            if ($assocId > 0) {
                $ignorados++;

                continue;
            }
            if (! $incluir) {
                continue;
            }

            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }

            $categoriaId = isset($row['categoria_id']) && $row['categoria_id'] !== '' ? (int) $row['categoria_id'] : null;
            $preco = $this->parseDecimal($row['preco'] ?? 0);
            $qtdEmb = $this->parseDecimal($row['qtd_embalagem'] ?? 0);
            $unEmb = trim((string) ($row['un_embalagem'] ?? 'g'));
            if (! in_array($unEmb, ['g', 'kg', 'ml', 'l', 'un'], true)) {
                $unEmb = 'g';
            }

            $produtoModel->insert([
                'categoria_id' => $categoriaId,
                'nome' => $nome,
                'embalagem' => trim((string) ($row['embalagem'] ?? '')) ?: null,
                'preco' => $preco,
                'qtd_embalagem' => $qtdEmb,
                'un_embalagem' => $unEmb,
                'observacoes' => ! empty($row['observacoes']) ? trim((string) $row['observacoes']) : null,
            ]);
            $criados++;
        }

        $model->update($id, [
            'status' => 'processado',
            'linhas_json' => json_encode($this->sanitizeRowsForStorage($rows), JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()->to('/produtos')->with(
            'success',
            'Importação concluída. Produtos novos: ' . $criados . '. Linhas associadas a produto existente (não duplicadas): ' . $ignorados . '.'
        );
    }

    /**
     * @param callable(string): void|null $onLog
     * @return array{
     *   success: bool,
     *   errors: list<string>,
     *   import_id: ?int,
     *   flash_warning: ?string,
     *   debug: array<string, mixed>
     * }
     */
    private function executarImportacaoCupom(?callable $onLog, bool $setSessionFlashOnIaWarning): array
    {
        $log = static function (string $m) use ($onLog): void {
            if ($onLog !== null) {
                $onLog($m);
            }
        };

        $texto = trim((string) $this->request->getPost('texto_cupom'));
        $file = $this->request->getFile('arquivo');

        if ($texto === '' && (! $file || ! $file->isValid())) {
            return [
                'success' => false,
                'errors' => ['Envie um arquivo ou cole o texto do cupom.'],
                'import_id' => null,
                'flash_warning' => null,
                'debug' => [],
            ];
        }

        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $allowed = ['txt', 'pdf', 'jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower((string) $file->getExtension());
            if (! in_array($ext, $allowed, true)) {
                return [
                    'success' => false,
                    'errors' => ['Formato não suportado. Use TXT, PDF ou imagem (JPG, PNG, WEBP).'],
                    'import_id' => null,
                    'flash_warning' => null,
                    'debug' => [],
                ];
            }
        }

        $extraTexto = $texto;
        $arquivoOriginal = null;
        $arquivoPath = null;
        $textoIa = null;
        $provedorIaSalvo = null;
        $flashWarning = null;

        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $ext = strtolower((string) $file->getExtension());
            $log('Arquivo: ' . $file->getClientName() . ' (extensao .' . $ext . ')');

            if ($ext === 'txt') {
                $tmp = $file->getTempName();
                if ($tmp && is_readable($tmp)) {
                    $conteudoTxt = (string) file_get_contents($tmp);
                    $extraTexto .= "\n" . $conteudoTxt;
                    $log('TXT lido: ' . strlen($conteudoTxt) . ' caracteres.');
                }
            }

            $log('Salvando upload em disco...');
            $targetDir = WRITEPATH . 'uploads/cupons';
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $newName = $file->getRandomName();
            $file->move($targetDir, $newName);
            $arquivoOriginal = $file->getClientName();
            $arquivoPath = 'uploads/cupons/' . $newName;

            $savedFullPath = $targetDir . DIRECTORY_SEPARATOR . $newName;
            $mimeParaIa = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                default => null,
            };

            $cupomCfg = config('CupomFiscal');
            if ($mimeParaIa !== null && $cupomCfg instanceof CupomFiscal && is_readable($savedFullPath)) {
                $log('Preparando leitura por IA (mime ' . $mimeParaIa . ')...');
                $ia = $this->tentarExtrairTextoIa($savedFullPath, $mimeParaIa, $cupomCfg, $log);
                if ($ia['text'] !== null && $ia['text'] !== '') {
                    $textoIa = $ia['text'];
                    $provedorIaSalvo = $ia['provedor'];
                    $extraTexto .= ($extraTexto !== '' ? "\n\n" : '') . $textoIa;
                    $log('IA (' . ($provedorIaSalvo ?? '?') . ') retornou ' . strlen($textoIa) . ' caracteres.');
                } else {
                    $log('IA não retornou texto utilizável.');
                }
                if ($ia['warning'] !== null) {
                    $msg = 'Leitura automatica (IA): ' . $ia['warning'] . ' Cole o texto do cupom no campo abaixo ou tente outra foto.';
                    $flashWarning = $msg;
                    if ($setSessionFlashOnIaWarning) {
                        session()->setFlashdata('warning', $msg);
                    }
                    $log('Aviso: ' . $ia['warning']);
                }
            } else {
                $log('IA ignorada (tipo sem suporte ou arquivo ilegivel).');
            }
        } else {
            $log('Sem arquivo novo; usando apenas texto colado (' . strlen($texto) . ' caracteres).');
        }

        $extraNormalizado = $this->normalizarTextoBrutoCupom($extraTexto);
        $log('Texto bruto total (apos normalizacao): ' . strlen($extraNormalizado) . ' caracteres.');

        $linhas = $this->extrairLinhasDeTexto($extraNormalizado);
        $log('Linhas candidatas a itens: ' . count($linhas) . '.');

        if ($linhas === []) {
            $linhas = [
                $this->linhaVazia(),
            ];
            $log('Nenhuma linha extraida; criada linha em branco para preenchimento manual.');
        }

        $previewLinhas = array_slice(array_map(static fn ($r) => (string) ($r['nome'] ?? ''), $linhas), 0, 8);
        $previewLinhas = array_filter($previewLinhas, static fn ($n) => $n !== '');

        $partesAux = [];
        if ($texto !== '') {
            $partesAux[] = $texto;
        }
        if ($textoIa !== null && $textoIa !== '') {
            $rotuloIa = $provedorIaSalvo === 'ocrspace'
                ? 'OCR.space'
                : ($provedorIaSalvo === 'ollama' ? 'Ollama' : ($provedorIaSalvo === 'gemini' ? 'Gemini' : 'IA'));
            $partesAux[] = '--- Texto extraido por ' . $rotuloIa . " ---\n" . $textoIa;
        }
        $textoAuxiliar = $partesAux !== [] ? implode("\n\n", $partesAux) : null;

        $log('Gravando registro no banco de dados...');
        $model = new CupomFiscalImportModel();
        $id = $model->insert([
            'arquivo_original' => $arquivoOriginal,
            'arquivo_path' => $arquivoPath,
            'texto_auxiliar' => $textoAuxiliar,
            'linhas_json' => json_encode($linhas, JSON_UNESCAPED_UNICODE),
            'status' => 'rascunho',
        ], true);

        if ($id === false) {
            $dbErr = $model->errors();
            $detail = $dbErr !== [] ? ' ' . implode(' ', $dbErr) : '';

            return [
                'success' => false,
                'errors' => ['Não foi possível salvar a importação no banco.' . $detail],
                'import_id' => null,
                'flash_warning' => null,
                'debug' => [],
            ];
        }

        $debug = [
            'extra_texto_len' => strlen($extraNormalizado),
            'linhas_count' => count($linhas),
            'ia_texto_len' => $textoIa !== null ? strlen($textoIa) : 0,
            'ia_provedor' => $provedorIaSalvo,
            'preview_nomes' => array_values($previewLinhas),
            'trecho_texto' => mb_substr($extraNormalizado, 0, 400),
        ];

        return [
            'success' => true,
            'errors' => [],
            'import_id' => (int) $id,
            'flash_warning' => $flashWarning,
            'debug' => $debug,
        ];
    }

    /**
     * @param callable(string): void|null $onLog
     * @return array{text: ?string, provedor: ?string, warning: ?string}
     */
    private function tentarExtrairTextoIa(string $savedFullPath, string $mimeParaIa, CupomFiscal $cfg, ?callable $onLog = null): array
    {
        $logIa = static function (string $m) use ($onLog): void {
            if ($onLog !== null) {
                $onLog($m);
            }
        };

        $provider = strtolower(trim($cfg->iaProvider));
        if (! in_array($provider, ['auto', 'ocrspace', 'ollama', 'gemini'], true)) {
            $provider = 'auto';
        }

        $hasOcrSpace = trim((string) $cfg->ocrSpaceApiKey) !== '';
        $hasOllama = trim((string) $cfg->ollamaBaseUrl) !== '';
        $hasGemini = $cfg->geminiApiKey !== '';

        $tryOcrSpace = static function () use ($savedFullPath, $mimeParaIa, $cfg): array {
            return (new CupomFiscalOcrSpaceExtractor($cfg))->extractFromFile($savedFullPath, $mimeParaIa);
        };

        $tryOllama = static function () use ($savedFullPath, $mimeParaIa, $cfg): array {
            return (new CupomFiscalOllamaExtractor($cfg))->extractFromFile($savedFullPath, $mimeParaIa);
        };

        $tryGemini = static function () use ($savedFullPath, $mimeParaIa, $cfg): array {
            return (new CupomFiscalGeminiExtractor($cfg))->extractFromFile($savedFullPath, $mimeParaIa);
        };

        if ($provider === 'ocrspace') {
            if (! $hasOcrSpace) {
                return ['text' => null, 'provedor' => null, 'warning' => 'iaProvider=ocrspace mas cupomfiscal.ocrSpaceApiKey está vazio.'];
            }
            $logIa('OCR.space: enviando imagem (aguarde)...');
            $res = $tryOcrSpace();
            if ($res['ok'] && ! empty($res['text'])) {
                $logIa('OCR.space: concluido com sucesso.');

                return ['text' => $res['text'], 'provedor' => 'ocrspace', 'warning' => null];
            }
            $err = (string) ($res['error'] ?? 'falha OCR.space');
            $logIa('OCR.space: erro — ' . $err);

            return ['text' => null, 'provedor' => null, 'warning' => $err];
        }

        if ($provider === 'ollama') {
            if (! $hasOllama) {
                return ['text' => null, 'provedor' => null, 'warning' => 'iaProvider=ollama mas cupomfiscal.ollamaBaseUrl está vazio.'];
            }
            $logIa('Ollama: enviando imagem ao modelo ' . $cfg->ollamaModel . ' (aguarde)...');
            $res = $tryOllama();
            if ($res['ok'] && ! empty($res['text'])) {
                $logIa('Ollama: concluido com sucesso.');

                return ['text' => $res['text'], 'provedor' => 'ollama', 'warning' => null];
            }
            $logIa('Ollama: erro — ' . (string) ($res['error'] ?? 'desconhecido'));

            return ['text' => null, 'provedor' => null, 'warning' => (string) ($res['error'] ?? 'falha Ollama')];
        }

        if ($provider === 'gemini') {
            if (! $hasGemini) {
                return ['text' => null, 'provedor' => null, 'warning' => 'iaProvider=gemini mas cupomfiscal.geminiApiKey está vazio.'];
            }
            $logIa('Gemini: enviando imagem (aguarde)...');
            $res = $tryGemini();
            if ($res['ok'] && ! empty($res['text'])) {
                $logIa('Gemini: concluido com sucesso.');

                return ['text' => $res['text'], 'provedor' => 'gemini', 'warning' => null];
            }
            $err = (string) ($res['error'] ?? 'falha Gemini');
            $logIa('Gemini: erro — ' . $err);

            return ['text' => null, 'provedor' => null, 'warning' => $err === 'API não configurada' ? 'Gemini sem chave configurada.' : $err];
        }

        // auto: OCR.space primeiro (OCR dedicado), depois Ollama local, depois Gemini
        $avisos = [];
        if ($hasOcrSpace) {
            $logIa('Auto: tentando OCR.space primeiro...');
            $resApi = $tryOcrSpace();
            if ($resApi['ok'] && ! empty($resApi['text'])) {
                $logIa('OCR.space respondeu com sucesso.');

                return ['text' => $resApi['text'], 'provedor' => 'ocrspace', 'warning' => null];
            }
            $avisos[] = (string) ($resApi['error'] ?? 'OCR.space falhou');
            $logIa('OCR.space falhou: ' . ($resApi['error'] ?? ''));
        }

        if ($hasOllama) {
            $logIa('Auto: tentando Ollama primeiro...');
            $resO = $tryOllama();
            if ($resO['ok'] && ! empty($resO['text'])) {
                $logIa('Ollama respondeu; pulando Gemini.');

                return ['text' => $resO['text'], 'provedor' => 'ollama', 'warning' => null];
            }
            if (($resO['error'] ?? '') !== 'Ollama não configurado') {
                $avisos[] = (string) ($resO['error'] ?? 'Ollama falhou');
                $logIa('Ollama falhou: ' . ($resO['error'] ?? ''));
            }
        }

        if ($hasGemini) {
            $logIa('Tentando Gemini...');
            $resG = $tryGemini();
            if ($resG['ok'] && ! empty($resG['text'])) {
                $logIa('Gemini respondeu com sucesso.');

                return ['text' => $resG['text'], 'provedor' => 'gemini', 'warning' => null];
            }
            $ge = (string) ($resG['error'] ?? 'Gemini falhou');
            if ($ge !== 'API não configurada') {
                $avisos[] = $ge;
                $logIa('Gemini falhou: ' . $ge);
            }
        }

        if ($avisos === []) {
            return ['text' => null, 'provedor' => null, 'warning' => 'Nenhum provedor de IA configurado (ocrSpaceApiKey, ollamaBaseUrl ou geminiApiKey).'];
        }

        return ['text' => null, 'provedor' => null, 'warning' => implode(' | ', $avisos)];
    }

    /** Remove ruido comum de respostas de IA (markdown, etc.). */
    private function normalizarTextoBrutoCupom(string $texto): string
    {
        $texto = preg_replace("/\r\n?/", "\n", $texto);
        $texto = preg_replace('/^```[\w]*\s*\n/m', '', $texto);
        $texto = preg_replace('/\n```\s*$/m', '', $texto);
        $texto = preg_replace('/```/', '', $texto);

        return trim($texto);
    }

    /**
     * Unidades comuns em cupom SAT / PDV → valores do cadastro de produto.
     */
    private function normalizarUnidadeCupom(string $u): string
    {
        $k = strtoupper(trim($u));

        return match ($k) {
            'UN', 'UND', 'PC', 'PCT', 'CX', 'PÇ', 'PCO' => 'un',
            'KG' => 'kg',
            'G' => 'g',
            'ML' => 'ml',
            'L' => 'l',
            default => 'un',
        };
    }

    /**
     * Cabeçalhos, rodapés e linhas de cupom que não são itens.
     */
    private function linhaEhRuidoCupom(string $line): bool
    {
        $l = trim($line);
        if ($l === '') {
            return true;
        }

        if (preg_match('/^[-=_*.\s]{4,}$/', $l)) {
            return true;
        }

        if (preg_match('/^subtotal\b/iu', $l)) {
            return true;
        }

        if (preg_match('/^total\s+R\$/iu', $l) || preg_match('/^total\s{2,}[\d.,]/iu', $l)) {
            return true;
        }

        if (preg_match('/^(troco|dinheiro|pix|desconto)\b/iu', $l)) {
            return true;
        }

        if (preg_match('/cart[aã]o|cr[eé]dito|d[eé]bito|vale\s+refei|pagamento|valor\s+pago/iu', $l)) {
            return true;
        }

        if (preg_match('/^operador\s*:/iu', $l)) {
            return true;
        }

        if (preg_match('/^obrigado\b|^volte\s+sempre/iu', $l)) {
            return true;
        }

        if (preg_match('#^https?://|^www\.#iu', $l)) {
            return true;
        }

        if (preg_match('/\bcupom\s+fiscal\b|\beletr(ô|o)nico\b|\bSAT\b|\bCF-?e\b|\bNFC-?e\b/iu', $l)) {
            return true;
        }

        if (preg_match('/\bCNPJ\b|\bCPF\b|\bIE\b\s*:/iu', $l)) {
            return true;
        }

        if (preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\s*$/', $l)) {
            return true;
        }

        // Linha só de loja / marca (sem preço de item)
        if (preg_match('/controle\s+na\s+m[aã]o|^CNM\b/iu', $l) && ! preg_match('/\d+[.,]\d{2}\s*$/', $l)) {
            return true;
        }

        // Cabeçalho de colunas do cupom
        if (preg_match('/\bITEM\b.*\bDESCRI/i', $l) || preg_match('/\bVL\.?\s*(ITEM|IEM)\b/iu', $l)) {
            return true;
        }

        if (preg_match('/^(c[oó]d|código|qtd|quant|un\.?|vl\.?)\s*$/iu', $l)) {
            return true;
        }

        return false;
    }

    /**
     * Formato típico SAT/PDV: "1 001 NOME DO PRODUTO UN 1 35,00" (código interno opcional).
     *
     * @return ?array{nome: string, un_embalagem: string, qtd_embalagem: float, preco: float}
     */
    private function tryParseLinhaFormatoSat(string $line): ?array
    {
        $line = (string) preg_replace('/\s+/', ' ', trim($line));
        $line = (string) preg_replace('/\b(UN|UND|PC|PCT|CX|KG|G|ML|L)\./iu', '$1 ', $line);
        $line = trim((string) preg_replace('/\s+/', ' ', $line));
        if ($line === '') {
            return null;
        }

        if (! preg_match('/(?:R\$\s*)?(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})\s*$/u', $line, $mPrice)) {
            return null;
        }

        $precoStr = $mPrice[1];
        $beforePrice = trim(mb_substr($line, 0, mb_strlen($line) - mb_strlen($mPrice[0])));

        if ($beforePrice === '' || ! preg_match('/\s(\d+(?:[.,]\d+)?)\s*$/u', $beforePrice, $mQtd)) {
            return null;
        }

        $qtdStr = $mQtd[1];
        $beforeQtd = trim(mb_substr($beforePrice, 0, mb_strlen($beforePrice) - mb_strlen($mQtd[0])));

        $units = 'UN|UND|PC|PCT|CX|KG|G|ML|L';
        if (! preg_match('/\s(' . $units . ')\s*$/iu', $beforeQtd, $mUn)) {
            return null;
        }

        $unitRaw = $mUn[1];
        $beforeUnit = trim(mb_substr($beforeQtd, 0, mb_strlen($beforeQtd) - mb_strlen($mUn[0])));

        if ($beforeUnit === '') {
            return null;
        }

        $nome = $beforeUnit;
        if (preg_match('/^(\d{1,2})\s+(\d{2,4})\s+(.+)$/u', $beforeUnit, $mHead)) {
            $nome = trim($mHead[3]);
        } elseif (preg_match('/^(\d{1,2})\s+(.+)$/u', $beforeUnit, $mHead2)) {
            $nome = trim($mHead2[2]);
        }

        if ($nome === '' || mb_strlen($nome) < 2) {
            return null;
        }

        return [
            'nome' => $nome,
            'un_embalagem' => $this->normalizarUnidadeCupom($unitRaw),
            'qtd_embalagem' => $this->parseDecimal($qtdStr),
            'preco' => $this->parseDecimal($precoStr),
        ];
    }

    private function linhaParecePrecoNoFinal(string $line): bool
    {
        return (bool) preg_match('/(?:R\$\s*)?(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})\s*$/u', trim($line));
    }

    private function linhaSomentePreco(string $line): bool
    {
        return (bool) preg_match('/^\s*(?:R\$\s*)?(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})\s*$/u', trim($line));
    }

    private function extrairPrecoDaLinha(string $line): ?float
    {
        if (preg_match('/(?:R\$\s*)?(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})\s*$/u', trim($line), $m)) {
            return $this->parseDecimal($m[1]);
        }

        return null;
    }

    private function limparNomeItemExtraido(string $nome): string
    {
        $nome = trim($nome);
        // GTIN/EAN-13 no inicio (com/sem separadores, com/sem espaco apos codigo)
        $nome = (string) preg_replace('/^\s*(?:\d[\s-]*){13}(?=\D|$)\s*/u', '', $nome);
        $nome = (string) preg_replace('/^\s*\(?\d{13}\)?(?=\D|$)\s*/u', '', $nome);
        $nome = (string) preg_replace('/^\d{1,2}\s+\d{2,5}\s+/u', '', $nome);
        $nome = (string) preg_replace('/^\d{1,2}\s+/u', '', $nome);
        $nome = (string) preg_replace('/\s+(?:R\$\s*)?\d{1,3}(?:\.\d{3})*,\d{2}\s*$/u', '', $nome);
        $nome = trim((string) preg_replace('/\s{2,}/', ' ', $nome));

        return $nome;
    }

    private function normalizarChaveNomeItem(string $nome): string
    {
        $n = mb_strtolower(trim($nome));
        $n = (string) preg_replace('/[^a-z0-9à-ÿ\s]/iu', ' ', $n);
        $n = trim((string) preg_replace('/\s+/', ' ', $n));

        return $n;
    }

    private function scoreLinhaItem(array $row): int
    {
        $score = 0;
        if (trim((string) ($row['nome'] ?? '')) !== '') {
            $score += 4;
        }
        if (((float) ($row['preco'] ?? 0.0)) > 0.0) {
            $score += 3;
        }
        if (((float) ($row['qtd_embalagem'] ?? 0.0)) > 0.0) {
            $score += 1;
        }
        if (trim((string) ($row['un_embalagem'] ?? '')) !== '') {
            $score += 1;
        }

        return $score;
    }

    /**
     * Junta duplicatas parciais de OCR (mesmo produto em linhas consecutivas).
     *
     * @param list<array<string, mixed>> $linhas
     * @return list<array<string, mixed>>
     */
    private function consolidarLinhasParecidas(array $linhas): array
    {
        if (count($linhas) < 2) {
            return $linhas;
        }

        $out = [];
        foreach ($linhas as $row) {
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome === '') {
                $out[] = $row;

                continue;
            }

            $chaveAtual = $this->normalizarChaveNomeItem($nome);
            $precoAtual = (float) ($row['preco'] ?? 0.0);
            $qtdAtual = (float) ($row['qtd_embalagem'] ?? 0.0);
            $merged = false;

            for ($j = count($out) - 1; $j >= 0; $j--) {
                $prev = $out[$j];
                $nomePrev = trim((string) ($prev['nome'] ?? ''));
                if ($nomePrev === '') {
                    continue;
                }

                $chavePrev = $this->normalizarChaveNomeItem($nomePrev);
                if ($chavePrev === '') {
                    continue;
                }

                $pareceMesmo = $chaveAtual === $chavePrev
                    || str_contains($chaveAtual, $chavePrev)
                    || str_contains($chavePrev, $chaveAtual);

                if (! $pareceMesmo) {
                    // Para evitar juntar itens distantes por coincidência textual
                    if (count($out) - 1 - $j > 2) {
                        break;
                    }

                    continue;
                }

                $precoPrev = (float) ($prev['preco'] ?? 0.0);
                $qtdPrev = (float) ($prev['qtd_embalagem'] ?? 0.0);
                if ($precoAtual > 0.0 && $precoPrev > 0.0 && abs($precoAtual - $precoPrev) > 0.01) {
                    continue;
                }
                if ($qtdAtual > 0.0 && $qtdPrev > 0.0 && abs($qtdAtual - $qtdPrev) > 0.001) {
                    continue;
                }

                $escolhido = $this->scoreLinhaItem($row) >= $this->scoreLinhaItem($prev) ? $row : $prev;
                $out[$j] = array_merge($prev, $row, $escolhido);
                if ($nomePrev !== '' && mb_strlen($nomePrev) > mb_strlen((string) ($out[$j]['nome'] ?? ''))) {
                    $out[$j]['nome'] = $nomePrev;
                }
                if ($nome !== '' && mb_strlen($nome) > mb_strlen((string) ($out[$j]['nome'] ?? ''))) {
                    $out[$j]['nome'] = $nome;
                }
                $merged = true;
                break;
            }

            if (! $merged) {
                $out[] = $row;
            }
        }

        return array_values($out);
    }

    private function linhaPareceFragmentoNome(string $line): bool
    {
        $l = trim($line);
        if ($l === '') {
            return false;
        }
        if ($this->linhaSomentePreco($l) || $this->linhaParecePrecoNoFinal($l)) {
            return false;
        }

        return (bool) preg_match('/[A-Za-zÀ-ÿ]/u', $l);
    }

    /**
     * @return ?array{nome: string, qtd_embalagem: float, un_embalagem: string, preco: float}
     */
    private function parseLinhaComPrecoDetalhada(string $line): ?array
    {
        $line = trim($line);
        if (! $this->linhaParecePrecoNoFinal($line)) {
            return null;
        }

        if (preg_match('/^(.+?)\s+(\d+(?:[.,]\d+)?)\s*(UN|UND|PC|KG|G|ML|L)\s+R?\$?\s*(\d+[.,]\d{2})\s*$/iu', $line, $m)) {
            return [
                'nome' => $this->limparNomeItemExtraido((string) $m[1]),
                'qtd_embalagem' => $this->parseDecimal($m[2]),
                'un_embalagem' => $this->normalizarUnidadeCupom($m[3]),
                'preco' => $this->parseDecimal($m[4]),
            ];
        }

        if (preg_match('/^(.+?)\s+(\d+(?:[.,]\d+)?)\s*(?:UN|UND|PC|KG|G|ML|L)?\s+R?\$?\s*(\d+[.,]\d{2})\s*$/iu', $line, $m)) {
            return [
                'nome' => $this->limparNomeItemExtraido((string) $m[1]),
                'qtd_embalagem' => $this->parseDecimal($m[2]),
                'un_embalagem' => 'un',
                'preco' => $this->parseDecimal($m[3]),
            ];
        }

        if (preg_match('/^(.+?)\s+(\d+[.,]\d{2})\s*$/u', $line, $m)) {
            return [
                'nome' => $this->limparNomeItemExtraido((string) $m[1]),
                'qtd_embalagem' => 1.0,
                'un_embalagem' => 'un',
                'preco' => $this->parseDecimal($m[2]),
            ];
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extrairLinhasDeTexto(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        if (! str_contains($texto, "\n") && str_contains($texto, ';')) {
            $texto = str_replace(';', "\n", $texto);
        }

        $linhasBrutas = preg_split('/\n/', $texto) ?: [];
        $linhas = [];
        $count = count($linhasBrutas);

        for ($i = 0; $i < $count; $i++) {
            $line = $linhasBrutas[$i];
            $line = trim($line);
            $line = preg_replace('/^[-*]\s+/', '', $line) ?? '';
            $line = preg_replace('/^\d+[.)]\s+/', '', $line) ?? '';
            $line = trim((string) $line);

            if ($line === '' || mb_strlen($line) < 2) {
                continue;
            }

            if ($this->linhaEhRuidoCupom($line)) {
                continue;
            }

            $lineNorm = (string) preg_replace('/(\d)\s*(UN|UND|PC|PCT|CX|KG|G|ML|L)\b/iu', '$1 $2', $line);

            if ($this->linhaSomentePreco($lineNorm)) {
                $precoLinha = $this->extrairPrecoDaLinha($lineNorm);
                if ($precoLinha !== null && $linhas !== []) {
                    $lastIdx = count($linhas) - 1;
                    if (($linhas[$lastIdx]['preco'] ?? 0.0) <= 0.0 && trim((string) ($linhas[$lastIdx]['nome'] ?? '')) !== '') {
                        $linhas[$lastIdx]['preco'] = $precoLinha;

                        continue;
                    }
                }
            }

            $sat = $this->tryParseLinhaFormatoSat($lineNorm);
            if ($sat !== null) {
                $row = $this->linhaVazia();
                $row['nome'] = $this->limparNomeItemExtraido((string) $sat['nome']);
                $row['un_embalagem'] = $sat['un_embalagem'];
                $row['qtd_embalagem'] = $sat['qtd_embalagem'];
                $row['preco'] = $sat['preco'];
                $linhas[] = $row;

                continue;
            }

            if (str_contains($lineNorm, '|')) {
                $parts = array_values(array_filter(array_map('trim', explode('|', $lineNorm)), static fn ($p) => $p !== ''));
                if (count($parts) >= 2) {
                    $nomeCandidato = $parts[0];
                    if (preg_match('/^(nome|produto|item|desc|descri[cç][aã]o|qtd|quant|valor|pre[cç]o)$/iu', $nomeCandidato) && count($parts) <= 4) {
                        continue;
                    }

                    $row = $this->linhaVazia();
                    $row['nome'] = $this->limparNomeItemExtraido($nomeCandidato);
                    if (preg_match('/^\d{1,2}\s+\d{2,4}\s+(.+)$/u', $row['nome'], $nm)) {
                        $row['nome'] = trim($nm[1]);
                    }
                    $last = $parts[count($parts) - 1];
                    if (preg_match('/R?\$?\s*(\d{1,3}(?:\.\d{3})*,\d{2}|\d+[.,]\d{2})/u', $last, $mm)) {
                        $row['preco'] = $this->parseDecimal($mm[1]);
                    }
                    if (count($parts) >= 3) {
                        $mid = $parts[1];
                        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(UN|UND|PC|KG|G|ML|L)?/iu', $mid, $mq)) {
                            $row['qtd_embalagem'] = $this->parseDecimal($mq[1]);
                            if (! empty($mq[2])) {
                                $row['un_embalagem'] = $this->normalizarUnidadeCupom($mq[2]);
                            }
                        }
                    }
                    $linhas[] = $row;

                    continue;
                }
            }

            if (! $this->linhaParecePrecoNoFinal($lineNorm)) {
                if ($i + 1 < $count) {
                    $next = trim((string) $linhasBrutas[$i + 1]);
                    $next = trim((string) (preg_replace('/^[-*]\s+/', '', $next) ?? ''));
                    $next = trim((string) (preg_replace('/^\d+[.)]\s+/', '', $next) ?? ''));
                    $nextNorm = (string) preg_replace('/(\d)\s*(UN|UND|PC|PCT|CX|KG|G|ML|L)\b/iu', '$1 $2', $next);
                    if (! $this->linhaEhRuidoCupom($next) && $this->linhaSomentePreco($next)) {
                        $p = $this->extrairPrecoDaLinha($next);
                        if ($p !== null) {
                            $row = $this->linhaVazia();
                            $row['nome'] = $this->limparNomeItemExtraido($line);
                            $row['preco'] = $p;
                            $linhas[] = $row;
                            $i++;

                            continue;
                        }
                    }

                    $nextDet = $this->parseLinhaComPrecoDetalhada($nextNorm);
                    if ($nextDet !== null && $this->linhaPareceFragmentoNome($line)) {
                        $row = $this->linhaVazia();
                        $row['nome'] = $this->limparNomeItemExtraido($line . ' ' . $nextDet['nome']);
                        $row['qtd_embalagem'] = $nextDet['qtd_embalagem'];
                        $row['un_embalagem'] = $nextDet['un_embalagem'];
                        $row['preco'] = $nextDet['preco'];
                        if ($row['nome'] !== '') {
                            $linhas[] = $row;
                            $i++;

                            continue;
                        }
                    }
                }
                continue;
            }

            $row = $this->linhaVazia();
            $row['nome'] = $this->limparNomeItemExtraido($lineNorm);

            $det = $this->parseLinhaComPrecoDetalhada($lineNorm);
            if ($det !== null) {
                $row['nome'] = $det['nome'];
                $row['qtd_embalagem'] = $det['qtd_embalagem'];
                $row['un_embalagem'] = $det['un_embalagem'];
                $row['preco'] = $det['preco'];
            }

            if ($row['nome'] !== '' && ! $this->linhaSomentePreco($row['nome'])) {
                $linhas[] = $row;
            }
        }

        return $this->consolidarLinhasParecidas($linhas);
    }

    /**
     * @return array<string, mixed>
     */
    private function linhaVazia(): array
    {
        return [
            'nome' => '',
            'embalagem' => '',
            'preco' => 0.0,
            'qtd_embalagem' => 1.0,
            'un_embalagem' => 'un',
            'observacoes' => '',
            'categoria_id' => null,
            'produto_associado_id' => null,
            'incluir' => true,
        ];
    }

    private function parseDecimal(mixed $value): float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        if (str_contains($raw, ',')) {
            $normalized = str_replace('.', '', $raw);
            $normalized = str_replace(',', '.', $normalized);

            return is_numeric($normalized) ? (float) $normalized : 0.0;
        }

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sanitizeRowsForStorage(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'nome' => trim((string) ($row['nome'] ?? '')),
                'embalagem' => trim((string) ($row['embalagem'] ?? '')),
                'preco' => $this->parseDecimal($row['preco'] ?? 0),
                'qtd_embalagem' => $this->parseDecimal($row['qtd_embalagem'] ?? 0),
                'un_embalagem' => (string) ($row['un_embalagem'] ?? 'g'),
                'observacoes' => trim((string) ($row['observacoes'] ?? '')),
                'produto_associado_id' => isset($row['produto_associado_id']) && $row['produto_associado_id'] !== ''
                    ? (int) $row['produto_associado_id']
                    : null,
                'incluir' => ! empty($row['incluir']),
            ];
        }

        return $out;
    }
}
