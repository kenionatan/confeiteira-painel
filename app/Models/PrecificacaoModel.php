<?php

namespace App\Models;

use CodeIgniter\Model;

class PrecificacaoModel extends Model
{
    protected $table = 'precificacoes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'nome_produto',
        'categoria',
        'custo',
        'margem_percentual',
        'preco_sugerido',
        'observacoes',
    ];
    protected $useTimestamps = true;
}
