<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;

class PainelController extends BaseController
{
    public function index(): string
    {
        $db = \Config\Database::connect();

        $totalClientes = (int) $db->table('clientes')->countAllResults();
        $subscriptionsActive = $this->countSubscriptionsByStatus($db, 'active');
        $subscriptionsTrial = $this->countSubscriptionsByStatus($db, 'trial');
        $totalPlans = (int) $db->table('plans')->countAllResults();

        $recentClientes = $db->table('clientes')
            ->orderBy('id', 'DESC')
            ->limit(8)
            ->get()
            ->getResultArray();

        return view('painel/dashboard', [
            'title'               => 'Dashboard',
            'totalClientes'       => $totalClientes,
            'subscriptionsActive' => $subscriptionsActive,
            'subscriptionsTrial'  => $subscriptionsTrial,
            'totalPlans'          => $totalPlans,
            'recentClientes'      => $recentClientes,
        ]);
    }

    private function countSubscriptionsByStatus(BaseConnection $db, string $status): int
    {
        return (int) $db->table('subscriptions')->where('status', $status)->countAllResults();
    }
}
