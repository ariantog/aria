<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operation extends Model
{
    use HasFactory, SoftDeletes, FillsProductionColumnDefaults;

    protected $fillable = ['name', 'description'];

    public static function getPermissions(): array
    {
        return [
            // Operations
            'operation-list' => 'journal-operation-list',
            'operation-create' => 'journal-operation-create',
            'operation-edit' => 'journal-operation-edit',
            'operation-delete' => 'journal-operation-delete',

            // Account List
            'account-list' => 'journal-account-list',
            'account-create' => 'journal-account-create',
            'account-edit' => 'journal-account-edit',
            'account-delete' => 'journal-account-delete',
        ];
    }

    public function accounts()
    {
        return $this->hasMany(Addrbook::class, 'operation_id');
    }
}
