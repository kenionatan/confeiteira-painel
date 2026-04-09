<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoriaProdutoModel extends Model
{
    protected $table = 'categorias_produto';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['nome'];
    protected $useTimestamps = true;
}
