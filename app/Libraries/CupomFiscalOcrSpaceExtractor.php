<?php

namespace App\Libraries;

use Config\CupomFiscal;
use Config\Services;

/**
 * Extrai texto de cupom fiscal com OCR.space.
 */
class CupomFiscalOcrSpaceExtractor
{
    private const MAX_BYTES = 8 * 1024 * 1024;

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
        $apiKey = trim((string) $this->config->ocrSpaceApiKey);
        if ($apiKey === '') {
            return ['ok' => false, 'text' => null, 'error' => 'API OCR.space nao configurada'];
        }

        if (! is_readable($absolutePath)) {
            return ['ok' => false, 'text' => null, 'error' => 'Arquivo ilegivel'];
        }

        $size = filesize($absolutePath);
        if ($size === false || $size > self::MAX_BYTES) {
            return ['ok' => false, 'text' => null, 'error' => 'Arquivo muito grande para OCR.space (max ~8 MB)'];
        }

        $mime = $this->normalizeMime($mimeType);
        if ($mime === null) {
            return ['ok' => false, 'text' => null, 'error' => 'OCR.space aqui aceita apenas imagem (JPG/PNG/WEBP).'];
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return ['ok' => false, 'text' => null, 'error' => 'Leitura vazia'];
        }

        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($raw);
        $endpoint = trim((string) $this->config->ocrSpaceEndpoint);
        if ($endpoint === '') {
            $endpoint = 'https://api.ocr.space/parse/image';
        }

        try {
            $client = Services::curlrequest([
                'timeout' => 120,
                'connect_timeout' => 20,
            ], null, null, false);

            $response = $client->post($endpoint, [
                'form_params' => [
                    'base64Image' => $dataUri,
                    'language' => 'por',
                    'isOverlayRequired' => 'false',
                    'scale' => 'true',
                    'OCREngine' => '2',
                ],
                'headers' => [
                    'apikey' => $apiKey,
                ],
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Cupom OCR.space: requisicao falhou: ' . $e->getMessage());

            return ['ok' => false, 'text' => null, 'error' => 'Falha de rede no OCR.space'];
        }

        $code = $response->getStatusCode();
        $respBody = (string) $response->getBody();
        $json = json_decode($respBody, true);

        if ($code !== 200 || ! is_array($json)) {
            return ['ok' => false, 'text' => null, 'error' => 'OCR.space HTTP ' . $code];
        }

        if (! empty($json['IsErroredOnProcessing'])) {
            $msg = '';
            if (! empty($json['ErrorMessage']) && is_array($json['ErrorMessage'])) {
                $msg = implode(' ', array_map(static fn ($v) => (string) $v, $json['ErrorMessage']));
            }
            if ($msg === '' && ! empty($json['ErrorDetails'])) {
                $msg = (string) $json['ErrorDetails'];
            }

            return ['ok' => false, 'text' => null, 'error' => $msg !== '' ? $msg : 'OCR.space falhou ao processar'];
        }

        $parsed = $json['ParsedResults'][0]['ParsedText'] ?? null;
        if (! is_string($parsed) || trim($parsed) === '') {
            return ['ok' => false, 'text' => null, 'error' => 'OCR.space retornou texto vazio'];
        }

        return ['ok' => true, 'text' => trim($parsed), 'error' => null];
    }

    private function normalizeMime(string $mime): ?string
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
