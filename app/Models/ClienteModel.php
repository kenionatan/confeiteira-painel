<?php

namespace App\Models;

use CodeIgniter\Model;

class ClienteModel extends Model
{
    protected $table = 'clientes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'dominio',
        'nome',
        'whatsapp',
        'email',
        'senha_hash',
        'cartao_token',
        'cartao_ultimos4',
        'cartao_bandeira',
    ];
    protected $useTimestamps = true;
}
