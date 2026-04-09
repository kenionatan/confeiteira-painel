<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'cliente_id',
        'name',
        'email',
        'password_hash',
        'preferred_theme',
        'is_owner',
        'is_active',
    ];
    protected $useTimestamps = true;
}
