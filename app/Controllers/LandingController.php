<?php

namespace App\Controllers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class LandingController extends BaseController
{
    private const SUPPORTED_LOCALES = ['pt', 'en', 'es'];

    private const LOCALE_COOKIE = 'landing_lang';

    public function index(): string
    {
        $locale = $this->resolveLocale();
        $this->request->setLocale($locale);
        service('language')->setLocale($locale);

        $tz = new DateTimeZone('America/Sao_Paulo');
        $offerEndsAt = (new DateTimeImmutable('now', $tz))
            ->modify('+90 minutes')
            ->format(DateTimeInterface::ATOM);

        return view('landing/index', [
            'locale'                 => $locale,
            'htmlLang'               => lang('Landing.html_lang'),
            'title'                  => lang('Landing.title'),
            'whatsappMessage'        => lang('Landing.whatsapp_message'),
            'offerEndsAt'            => $offerEndsAt,
            'offerEndedLabel'        => lang('Landing.offer_ended'),
            'numberLocale'           => $this->numberLocale($locale),
            'socialProofFakeClients' => $this->socialProofFakeClients(),
            'plans'                  => $this->plans(),
            'availableLocales'       => [
                'pt' => lang('Landing.lang_pt'),
                'en' => lang('Landing.lang_en'),
                'es' => lang('Landing.lang_es'),
            ],
        ]);
    }

    /**
     * Persiste o idioma escolhido e redireciona de volta à landing.
     */
    public function setLanguage(string $locale)
    {
        $locale = strtolower($locale);
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = 'en';
        }

        return redirect()->to('/')->setCookie([
            'name'     => self::LOCALE_COOKIE,
            'value'    => $locale,
            'expire'   => YEAR,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Ordem: cookie (escolha manual) → Accept-Language → inglês.
     */
    private function resolveLocale(): string
    {
        $fromCookie = strtolower((string) $this->request->getCookie(self::LOCALE_COOKIE));
        if (in_array($fromCookie, self::SUPPORTED_LOCALES, true)) {
            return $fromCookie;
        }

        return $this->localeFromAcceptLanguage(
            $this->request->getHeaderLine('Accept-Language')
        );
    }

    /**
     * Detecta pt/en/es a partir do header Accept-Language.
     * Qualquer outro idioma cai em inglês.
     */
    private function localeFromAcceptLanguage(string $header): string
    {
        if ($header === '') {
            return 'en';
        }

        $parts = array_map('trim', explode(',', $header));
        $ranked = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $q = 1.0;
            $lang = $part;
            if (str_contains($part, ';')) {
                [$lang, $params] = array_pad(explode(';', $part, 2), 2, '');
                $lang = trim($lang);
                if (preg_match('/q\s*=\s*([0-9.]+)/i', $params, $m)) {
                    $q = (float) $m[1];
                }
            }

            $primary = strtolower(strtok(str_replace('_', '-', $lang), '-'));
            if ($primary === 'pt' || $primary === 'en' || $primary === 'es') {
                $ranked[$primary] = max($ranked[$primary] ?? 0.0, $q);
            }
        }

        if ($ranked === []) {
            return 'en';
        }

        arsort($ranked);

        return (string) array_key_first($ranked);
    }

    private function numberLocale(string $locale): string
    {
        return match ($locale) {
            'pt' => 'pt-BR',
            'es' => 'es',
            default => 'en-US',
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            'free' => [
                'nome'          => lang('Landing.plan_free_name'),
                'valor'         => 'R$ 0,00',
                'valorAnterior' => '',
                'descricao'     => lang('Landing.plan_free_desc'),
                'destaque'      => false,
                'features'      => (array) lang('Landing.plan_free_features'),
            ],
            'basico' => [
                'nome'          => lang('Landing.plan_basico_name'),
                'valor'         => 'R$ 27,90',
                'valorAnterior' => 'R$ 57,90',
                'descricao'     => lang('Landing.plan_basico_desc'),
                'destaque'      => false,
                'features'      => (array) lang('Landing.plan_basico_features'),
            ],
            'pro' => [
                'nome'          => lang('Landing.plan_pro_name'),
                'valor'         => 'R$ 34,90',
                'valorAnterior' => 'R$ 79,90',
                'descricao'     => lang('Landing.plan_pro_desc'),
                'destaque'      => true,
                'features'      => (array) lang('Landing.plan_pro_features'),
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function socialProofFakeClients(): array
    {
        return [
            'firstNames' => [
                'Carla Cakes', 'anamaria_cakes', 'Júlia Confeitaria', 'Tudo Doces', 'Açaí Deus é amor', 'Camila', 'Rafaela', 'Açaiteria da Rose', 'Bruna', 'Gabriela',
            ],
            'neighborhoods' => [
                'Centro', 'Vila Nova', 'Jardins', 'Boa Vista', 'Planalto', 'Alvorada', 'Santa Clara', 'Industrial',
            ],
            'cities' => [
                'São Paulo', 'Jundiaí', 'Indaiatuba', 'Campinas', 'Curitiba', 'Belo Horizonte', 'Goiânia', 'Recife', 'Fortaleza', 'Porto Alegre',
            ],
            'actions' => array_values((array) lang('Landing.social_actions')),
            'minuteHints' => array_values((array) lang('Landing.social_minute_hints')),
        ];
    }
}
