<?php

namespace App\Models;

use CodeIgniter\Model;

class CupomFiscalImportModel extends Model
{
    protected $table = 'cupom_fiscal_imports';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'arquivo_original',
        'arquivo_path',
        'texto_auxiliar',
        'linhas_json',
        'status',
    ];
    protected $useTimestamps = true;
}
