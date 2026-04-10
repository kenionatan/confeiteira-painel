<?php

namespace App\Controllers;

use App\Models\ClienteModel;

class ClientesController extends BaseController
{
    public function index(): string
    {
        $listData = $this->buildListData(false);

        return view('clientes/index', [
            'title' => 'Clientes',
            'clientes' => $listData['clientes'],
            'pager' => $listData['pager'],
            'filters' => ['q' => $listData['q']],
        ]);
    }

    public function listaAjax()
    {
        if (! $this->request->isAJAX()) {
            return redirect()->to('/painel/clientes');
        }

        $listData = $this->buildListData(true);
        return $this->response->setJSON([
            'tableBodyHtml' => view('clientes/_table_body', [
                'clientes' => $listData['clientes'],
            ]),
            'paginationHtml' => $listData['pager']->links(),
        ]);
    }

    public function detalhes(int $id)
    {
        if (! $this->request->isAJAX()) {
            return redirect()->to('/painel/clientes');
        }

        $clienteModel = new ClienteModel();
        $cliente = $clienteModel->find($id);
        if (! $cliente) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Cliente nao encontrado.']);
        }

        unset($cliente['senha_hash']);

        $db = \Config\Database::connect();
        $subs = $db->table('subscriptions')
            ->select('subscriptions.*, plans.slug AS plan_slug, plans.nome AS plan_nome, plans.valor_mensal AS plan_valor_mensal, plans.descricao AS plan_descricao')
            ->join('plans', 'plans.id = subscriptions.plan_id')
            ->where('subscriptions.cliente_id', $id)
            ->orderBy('subscriptions.id', 'DESC')
            ->get()
            ->getResultArray();

        $subIds = array_column($subs, 'id');
        $payments = [];
        if ($subIds !== []) {
            $payments = $db->table('subscription_payments')
                ->whereIn('subscription_id', $subIds)
                ->orderBy('paid_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()
                ->getResultArray();
        }

        $subById = [];
        foreach ($subs as $s) {
            $subById[(int) $s['id']] = $s;
        }

        foreach ($payments as &$p) {
            $sid = (int) ($p['subscription_id'] ?? 0);
            $p['plan_nome'] = $subById[$sid]['plan_nome'] ?? '';
        }
        unset($p);

        $current = null;
        foreach ($subs as $s) {
            if (in_array($s['status'], ['active', 'trial'], true)) {
                $current = $s;
                break;
            }
        }
        if ($current === null && $subs !== []) {
            $current = $subs[0];
        }

        return $this->response->setJSON([
            'cliente'        => $cliente,
            'subscription'   => $current,
            'subscriptions' => $subs,
            'payments'      => $payments,
        ]);
    }

    private function buildListData(bool $pagerLinksToAjax): array
    {
        $model = new ClienteModel();
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $model->groupStart()
                ->like('nome', $q)
                ->orLike('email', $q)
                ->orLike('whatsapp', $q)
                ->orLike('dominio', $q)
                ->groupEnd();
        }

        $clientes = $model->orderBy('nome', 'ASC')->paginate(10);
        $pager = $model->pager;
        $pager->setPath($pagerLinksToAjax ? 'painel/clientes/lista-ajax' : 'painel/clientes');

        return [
            'clientes' => $clientes,
            'pager' => $pager,
            'q' => $q,
        ];
    }
}
