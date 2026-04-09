<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cupom fiscal — leitura de imagem/PDF via IA.
 *
 * .env (prefixo CI4: cupomfiscal.*):
 * - cupomfiscal.iaProvider = auto|ocrspace|ollama|gemini
 * - cupomfiscal.ocrSpaceApiKey / cupomfiscal.ocrSpaceEndpoint
 * - cupomfiscal.ollamaBaseUrl = http://127.0.0.1:11434
 * - cupomfiscal.ollamaModel = llama3.2-vision (ou llava, qwen2-vl, minicpm-v, etc.)
 * - cupomfiscal.geminiApiKey / cupomfiscal.geminiModel
 */
class CupomFiscal extends BaseConfig
{
    /**
     * auto = tenta OCR.space; depois Ollama; depois Gemini.
     * ocrspace | ollama | gemini = forca um provedor.
     */
    public string $iaProvider = 'auto';

    /** OCR.space API key */
    public string $ocrSpaceApiKey = '';

    /** OCR.space endpoint (default: https://api.ocr.space/parse/image) */
    public string $ocrSpaceEndpoint = 'https://api.ocr.space/parse/image';

    /** URL base do Ollama (sem barra final). Ex.: http://127.0.0.1:11434 */
    public string $ollamaBaseUrl = '';

    /** Modelo com visao: llama3.2-vision, llava, qwen2-vl, minicpm-v, etc. */
    public string $ollamaModel = 'llama3.2-vision';

    public string $geminiApiKey = '';

    public string $geminiModel = 'gemini-2.0-flash';
}
