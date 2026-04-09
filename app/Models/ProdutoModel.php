<?php

namespace App\Models;

use CodeIgniter\Model;

class ProdutoModel extends Model
{
    protected $table = 'produtos';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'categoria_id',
        'nome',
        'embalagem',
        'preco',
        'qtd_embalagem',
        'un_embalagem',
        'observacoes',
    ];
    protected $useTimestamps = true;
}
