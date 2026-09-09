<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhColaborador extends Model
{
    protected $table = 'rrhh_colaboradors';

    protected $fillable = [
        'fullName',
        'departmento_id',
        'telefonoCorporativo',
        'status',
        'user_id',
    ];
}
