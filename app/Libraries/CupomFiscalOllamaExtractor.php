<?php

namespace App\Libraries;

use Config\CupomFiscal;
use Config\Services;

/**
 * Extrai texto de cupom fiscal (imagem) via Ollama local (/api/chat com modelo vision).
 * PDF não é suportado pelo Ollama neste fluxo — use imagem, texto manual ou Gemini.
 */
class CupomFiscalOllamaExtractor
{
    private const MAX_BYTES = 15 * 1024 * 1024;

    private CupomFiscal $config;

    public function __construct(?CupomFiscal $config = null)
    {
        $this->config = $config ?? config('CupomFiscal');
    }

    /**
     * @return array{ok: bool, text: ?string, error: ?string}
     */
    public function extractFromFile(string $absolutePath, string $mimeType): array
    {
        $base = trim(rtrim((string) $this->config->ollamaBaseUrl, '/'));
        if ($base === '') {
            return ['ok' => false, 'text' => null, 'error' => 'Ollama não configurado'];
        }

        if (! is_readable($absolutePath)) {
            return ['ok' => false, 'text' => null, 'error' => 'Arquivo ilegível'];
        }

        $size = filesize($absolutePath);
        if ($size === false || $size > self::MAX_BYTES) {
            return ['ok' => false, 'text' => null, 'error' => 'Arquivo muito grande (max ~15 MB)'];
        }

        $mimeType = $this->normalizeImageMime($mimeType);
        if ($mimeType === null) {
            return ['ok' => false, 'text' => null, 'error' => 'Ollama aqui aceita só imagem (JPG/PNG/WEBP). PDF use Gemini ou cole o texto.'];
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return ['ok' => false, 'text' => null, 'error' => 'Leitura vazia'];
        }

        $b64 = base64_encode($raw);

        $prompt = <<<'PROMPT'
Você lê cupons fiscais brasileiros (supermercado, padaria, etc.).
Extraia APENAS as linhas de PRODUTOS/SERVIÇOS comprados (itens com nome e valores).
Para cada item, uma linha de texto em português, preferindo o formato:
NOME_DO_PRODUTO | quantidade unidade | valor_total (ex.: 12,90 ou R$ 12,90)

Regras:
- Ignore cabeçalho, CNPJ, endereço, CPF, nome da loja, número do cupom, data/hora se não forem parte do item.
- Ignore linhas de SUBTOTAL, TOTAL, TROCO, FORMA DE PAGAMENTO, PIX, CARTÃO, desconto geral (a menos que seja linha de item).
- Se não conseguir separar quantidade, coloque só nome e valor na linha.
- Não use markdown nem listas; apenas linhas de texto soltas.
- Se a imagem não for legível, responda exatamente: NAO_LEGIVEL
PROMPT;

        $body = [
            'model' => $this->config->ollamaModel,
            'stream' => false,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                    'images' => [$b64],
                ],
            ],
        ];

        $url = $base . '/api/chat';

        try {
            $client = Services::curlrequest([
                'timeout' => 300,
                'connect_timeout' => 15,
            ], null, null, false);

            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Cupom Ollama: ' . $e->getMessage());

            return ['ok' => false, 'text' => null, 'error' => 'Falha ao conectar no Ollama (' . $e->getMessage() . ')'];
        }

        $code = $response->getStatusCode();
        $respBody = (string) $response->getBody();
        $json = json_decode($respBody, true);

        if ($code !== 200 || ! is_array($json)) {
            $msg = is_array($json) && isset($json['error']) ? (string) $json['error'] : ('HTTP ' . $code);
            log_message('error', 'Cupom Ollama: ' . $msg);

            return ['ok' => false, 'text' => null, 'error' => 'Ollama: ' . $msg];
        }

        $text = $json['message']['content'] ?? null;
        if (! is_string($text)) {
            return ['ok' => false, 'text' => null, 'error' => 'Resposta invalida do Ollama'];
        }

        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'text' => null, 'error' => 'Resposta vazia do Ollama'];
        }

        if (stripos($text, 'NAO_LEGIVEL') !== false) {
            return ['ok' => false, 'text' => null, 'error' => 'Modelo não leu o cupom'];
        }

        return ['ok' => true, 'text' => $text, 'error' => null];
    }

    private function normalizeImageMime(string $mime): ?string
    {
        $m = strtolower(trim($mime));
        $map = [
            'image/jpeg' => 'image/jpeg',
            'image/jpg' => 'image/jpeg',
            'image/png' => 'image/png',
            'image/webp' => 'image/webp',
        ];

        return $map[$m] ?? null;
    }
}
