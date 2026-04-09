<?php

namespace App\Libraries;

use Config\CupomFiscal;
use Config\Services;

/**
 * Extrai texto de cupom fiscal (imagem ou PDF) usando Google Gemini API (cota gratuita no AI Studio).
 */
class CupomFiscalGeminiExtractor
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
        $apiKey = $this->config->geminiApiKey;
        if ($apiKey === '') {
            return ['ok' => false, 'text' => null, 'error' => 'API nao configurada'];
        }

        if (! is_readable($absolutePath)) {
            return ['ok' => false, 'text' => null, 'error' => 'Arquivo ilegivel'];
        }

        $size = filesize($absolutePath);
        if ($size === false || $size > self::MAX_BYTES) {
            return ['ok' => false, 'text' => null, 'error' => 'Arquivo muito grande (max ~15 MB)'];
        }

        $mimeType = $this->normalizeMime($mimeType);
        if ($mimeType === null) {
            return ['ok' => false, 'text' => null, 'error' => 'Tipo nao suportado para IA'];
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return ['ok' => false, 'text' => null, 'error' => 'Leitura vazia'];
        }

        $b64 = base64_encode($raw);

        $prompt = <<<'PROMPT'
Voce le cupons fiscais brasileiros (supermercado, padaria, etc.).
Extraia APENAS as linhas de PRODUTOS/SERVICOS comprados (itens com nome e valores).
Para cada item, uma linha de texto em portugues, preferindo o formato:
NOME_DO_PRODUTO | quantidade unidade | valor_total (ex.: 12,90 ou R$ 12,90)

Regras:
- Ignore cabecalho, CNPJ, endereco, CPF, nome da loja, numero do cupom, data/hora se nao forem parte do item.
- Ignore linhas de SUBTOTAL, TOTAL, TROCO, FORMA DE PAGAMENTO, PIX, CARTAO, desconto geral (a menos que seja linha de item).
- Se nao conseguir separar quantidade, coloque so nome e valor na linha.
- Nao use markdown nem listas; apenas linhas de texto soltas.
- Se a imagem nao for legivel, responda exatamente: NAO_LEGIVEL
PROMPT;

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $b64,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.15,
                'maxOutputTokens' => 4096,
            ],
        ];

        $model = $this->config->geminiModel;
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . rawurlencode($apiKey);

        try {
            $client = Services::curlrequest([
                'timeout' => 120,
                'connect_timeout' => 30,
            ], null, null, false);

            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Cupom Gemini: requisicao falhou: ' . $e->getMessage());

            return ['ok' => false, 'text' => null, 'error' => 'Falha de rede na API'];
        }

        $code = $response->getStatusCode();
        $respBody = (string) $response->getBody();
        $json = json_decode($respBody, true);

        if ($code !== 200) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $code);
            log_message('error', 'Cupom Gemini: ' . $msg);

            return ['ok' => false, 'text' => null, 'error' => 'API: ' . $msg];
        }

        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            $reason = $json['candidates'][0]['finishReason'] ?? 'unknown';
            log_message('warning', 'Cupom Gemini: resposta vazia, finish=' . $reason);

            return ['ok' => false, 'text' => null, 'error' => 'Resposta vazia da IA'];
        }

        $text = trim($text);
        if (stripos($text, 'NAO_LEGIVEL') !== false) {
            return ['ok' => false, 'text' => null, 'error' => 'IA nao leu o cupom'];
        }

        return ['ok' => true, 'text' => $text, 'error' => null];
    }

    private function normalizeMime(string $mime): ?string
    {
        $m = strtolower(trim($mime));
        $map = [
            'image/jpeg' => 'image/jpeg',
            'image/jpg' => 'image/jpeg',
            'image/png' => 'image/png',
            'image/webp' => 'image/webp',
            'application/pdf' => 'application/pdf',
        ];

        return $map[$m] ?? null;
    }
}
