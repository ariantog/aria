<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $guarded = [];

    public static function table()
    {
        return 'customers';
    }

    const TYPE_CUSTOMER = 1;

    const TYPE_WAREHOUSE = 2;

    const TYPE_BANK = 3;

    const TYPE_SUPPLIER = 4;

    const TYPE_VWAREHOUSE = 5;

    const TYPE_VACCOUNT = 6;

    const TYPE_RESELLER = 7;

    const TYPE_ACCOUNT = 8;

    public static $types = [
        self::TYPE_CUSTOMER => 'Customer',
        self::TYPE_WAREHOUSE => 'Warehouse',
        self::TYPE_BANK => 'Banks',
        self::TYPE_SUPPLIER => 'Supplier',
        self::TYPE_VWAREHOUSE => 'V. Warehouse',
        self::TYPE_VACCOUNT => 'V. Account',
        self::TYPE_RESELLER => 'Reseller',
    ];

    public static $typesJSON = [
        ['id' => self::TYPE_CUSTOMER, 'name' => 'Customer'],
        ['id' => self::TYPE_WAREHOUSE, 'name' => 'Warehouse'],
        ['id' => self::TYPE_BANK, 'name' => 'Banks'],
        ['id' => self::TYPE_SUPPLIER, 'name' => 'Supplier'],
        ['id' => self::TYPE_VWAREHOUSE, 'name' => 'V. Warehouse'],
        ['id' => self::TYPE_VACCOUNT, 'name' => 'V. Account'],
        ['id' => self::TYPE_RESELLER, 'name' => 'Reseller'],
    ];

    public static $defaultWH = 2875;

    public static $notAdjustable = [Customer::TYPE_VACCOUNT, Customer::TYPE_VWAREHOUSE, Customer::TYPE_WAREHOUSE];

    public function stat()
    {
        return $this->hasOne('App\Models\CustomerStat');
    }

    public function locations()
    {
        return $this->belongsToMany('App\Models\Location', 'location_customer');
    }

    public function getLocation($data)
    {

        $local = $data->implode('name', ', ');

        return $local;

    }

    public function scopeAccounts($query)
    {
        return $query->where('type', '=', Customer::TYPE_ACCOUNT);
    }

    public function operation()
    {
        return $this->belongsTo('App\Models\Operation', 'parent_id');
    }

    public function gajis()
    {
        return $this->hasMany(Gajih::class, 'bank_id', 'id');
    }

    public function getDetailLink()
    {
        return $this->generateLink('detail');
    }

    protected function generateLink($action)
    {
        if (! $this->id) {
            return '';
        }
        switch ($this->type) {
            case self::TYPE_WAREHOUSE: $action = 'warehouse.'.$action;
                break;
            case self::TYPE_BANK: $action = 'account.'.$action;
                break;
            case self::TYPE_SUPPLIER: $action = 'supplier.'.$action;
                break;
            case self::TYPE_VWAREHOUSE: $action = 'vwarehouse.'.$action;
                break;
            case self::TYPE_VACCOUNT: $action = 'vaccount.'.$action;
                break;
            case self::TYPE_RESELLER: $action = 'reseller.'.$action;
                break;
            case self::TYPE_ACCOUNT:
                if ($action == 'detail') {
                    $action = 'account';
                }
                $action = 'operation.'.$action;
                break;
            default: $action = 'customer.'.$action;
                break;
        }

        return \URL::route($action, ['id' => $this->id]);
    }

    public function getTypeLabel()
    {
        return self::$types[$this->type] ?? '';
    }
}
