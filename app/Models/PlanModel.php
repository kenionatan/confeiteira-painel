<?php

namespace App\Models;

use CodeIgniter\Model;

class PlanModel extends Model
{
    protected $table = 'plans';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'slug',
        'nome',
        'descricao',
        'valor_mensal',
        'ativo',
    ];
    protected $useTimestamps = true;
}
