<?php

namespace App\Controllers;

class CheckoutController extends BaseController
{
    /**
     * Redireciona para o cadastro no painel (todos os planos usam o mesmo formulario).
     * URLs absolutas externas em checkoutLinks ainda sao suportadas.
     */
    public function redirect(string $plan)
    {
        $normalizedPlan = strtolower(trim($plan));
        $subscriptions = config('Subscriptions');
        $checkoutLinks = $subscriptions->checkoutLinks;

        if (! isset($checkoutLinks[$normalizedPlan])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $target = $checkoutLinks[$normalizedPlan];

        if (str_starts_with($target, '/')) {
            return redirect()->to(base_url(ltrim($target, '/')));
        }

        if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            return redirect()->to($target);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
}
