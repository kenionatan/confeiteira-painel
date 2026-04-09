<?php

namespace App\Controllers;

class CheckoutController extends BaseController
{
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

        return redirect()->to($target);
    }
}
