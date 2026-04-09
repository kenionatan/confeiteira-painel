<?php

namespace App\Controllers;

class PainelController extends BaseController
{
    public function index()
    {
        return redirect()->to('/painel/clientes');
    }
}
