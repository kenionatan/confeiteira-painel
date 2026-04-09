<?php

namespace App\Models;

use CodeIgniter\Model;

class PedidoModel extends Model
{
    protected $table = 'pedidos';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'cliente_nome',
        'produto',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'status',
        'data_entrega',
        'observacoes',
    ];
    protected $useTimestamps = true;
}
