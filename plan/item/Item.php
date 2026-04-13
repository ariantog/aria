<?php

namespace App\Models;

use App\Helpers\LocalHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';

    protected $fillable = ['name', 'code', 'pcode', 'price', 'cost', 'tag_ids', 'description', 'description2', 'url'];

    const BRAND_00 = 0;

    const BRAND_CA = 1;

    const BRAND_CC = 2;

    const BRAND_CB = 3;

    const BRAND_CD = 4;

    const BRAND_CR = 5;

    const BRAND_CN = 6;

    const BRAND_CM = 7;

    const BRAND_CX = 8;

    const BRAND_CS = 9;

    const BRAND_HJ = 10;

    const BRAND_CP = 11;

    const BRAND_CJ = 12;

    const BRAND_PL = 13;

    const BRAND_DC = 14;

    const BRAND_CE = 15;

    const BRAND_CI = 16;

    const BRAND_CX0 = 17;

    const BRAND_CX7 = 18;

    const BRAND_CX8 = 19;

    const BRAND_CX9 = 20;

    public static $brands = [
        self::BRAND_00 => 'No Brand',
        self::BRAND_CA => 'CA',
        self::BRAND_CC => 'CC',
        self::BRAND_CB => 'CB',
        self::BRAND_CD => 'CD',
        self::BRAND_CR => 'CR',
        self::BRAND_CN => 'CN',
        self::BRAND_CM => 'CM',
        self::BRAND_CX => 'CX',
        self::BRAND_CS => 'CS',
        self::BRAND_HJ => 'HJ',
        self::BRAND_CP => 'CP',
        self::BRAND_CJ => 'CJ',
        self::BRAND_PL => 'PL',
        self::BRAND_DC => 'DC',
        self::BRAND_CE => 'CE',
        self::BRAND_CI => 'CI',
        self::BRAND_CX0 => 'CX0',
        self::BRAND_CX7 => 'CX7',
        self::BRAND_CX8 => 'CX8',
        self::BRAND_CX9 => 'CX9',
    ];

    public static $brandsJSON = [
        ['id' => self::BRAND_00, 'name' => 'No Brand'],
        ['id' => self::BRAND_CA, 'name' => 'CA'],
        ['id' => self::BRAND_CC, 'name' => 'CC'],
        ['id' => self::BRAND_CB, 'name' => 'CB'],
        ['id' => self::BRAND_CD, 'name' => 'CD'],
        ['id' => self::BRAND_CR, 'name' => 'CR'],
        ['id' => self::BRAND_CN, 'name' => 'CN'],
        ['id' => self::BRAND_CM, 'name' => 'CM'],
        ['id' => self::BRAND_CX, 'name' => 'CX'],
        ['id' => self::BRAND_CS, 'name' => 'CS'],
        ['id' => self::BRAND_HJ, 'name' => 'HJ'],
        ['id' => self::BRAND_CP, 'name' => 'CP'],
        ['id' => self::BRAND_CJ, 'name' => 'CJ'],
        ['id' => self::BRAND_PL, 'name' => 'PL'],
        ['id' => self::BRAND_DC, 'name' => 'DC'],
        ['id' => self::BRAND_CE, 'name' => 'CE'],
        ['id' => self::BRAND_CI, 'name' => 'CI'],
        ['id' => self::BRAND_CX0, 'name' => 'CX0'],
        ['id' => self::BRAND_CX7, 'name' => 'CX7'],
        ['id' => self::BRAND_CX8, 'name' => 'CX8'],
        ['id' => self::BRAND_CX9, 'name' => 'CX9'],
    ];

    const TYPE_ITEM = 1;

    const TYPE_ASSET_LANCAR = 2;

    const TYPE_ASSET_TETAP = 3;

    const TYPE_SERVICE = 5;

    public static function table()
    {
        return 'items';
    }

    public static $types = [
        self::TYPE_ITEM => 'Item',
        self::TYPE_ASSET_LANCAR => 'Asset Lancar',
        self::TYPE_ASSET_TETAP => 'Asset Tetap',
    ];

    public function getItemCode()
    {
        switch ($this->type) {
            case self::TYPE_ASSET_LANCAR: $name = $this->code;
                break;
            default: $name = $this->name;
                break;
        }

        return $name;
    }

    public function getItemName()
    {
        switch ($this->type) {
            case self::TYPE_ASSET_LANCAR: $name = $this->name;
                break;
            default: $name = $this->group ? $this->group->alias : $this->alias;
                break;
        }

        return $name;
    }

    public function getOnlineName()
    {
        $name = 'CoreNation ';

        // asset lancar
        if ($this->type == ITEM::TYPE_ASSET_LANCAR) {
            return $name.$this->name;
        }

        // brand
        switch ($this->brand) {
            case Item::BRAND_CA: $name .= 'Premium '.$this->group->alias;
                break;
            case Item::BRAND_CX:
                $name .= 'Men ';
                if (intval(substr($this->pcode, 2, 1)) == 9) {
                    $name .= 'Elite ';
                }
                $name .= $this->group->alias;
                break;
            default:
                $name .= 'Active '.$this->group->alias;
                break;
        }

        return $name;
    }

    public function getItemInWarehouse($itemId)
    {
        $total = DB::table('items')->select([
            'items.id',
            DB::raw('SUM(warehouse_item.quantity) as total_quantity'),
        ])->where('items.id', '=', $itemId)->join('warehouse_item', 'items.id', '=', 'warehouse_item.item_id')->leftJoin('customers', 'customers.id', '=', 'warehouse_item.warehouse_id')->where('customers.type', '=', Customer::TYPE_WAREHOUSE)->where(function ($query) {
            $query->where('customers.deleted_at', '=', null)->orWhere('customers.deleted_at', '=', '0000-00-00 00:00:00');
        })->first();

        if (! $total) {
            return 0;
        }

        return $total->total_quantity;
    }

    public function getQtyWarehouse($itemId, $whId)
    {
        $data = WarehouseItem::where('item_id', $itemId)->where('warehouse_id', $whId)->first();

        if ($data) {
            $qty = $data->quantity;
        } else {
            $qty = 0.00;
        }

        return $qty;
    }

    public function warehouseItem(): HasMany
    {
        return $this->hasMany(WarehouseItem::class, 'item_id', 'id')->where('warehouse_id', 2875);
    }

    public function warehousesItemAlt()
    {
        return $this->hasMany(WarehouseItem::class, 'item_id', 'id');
    }

    public function tags()
    {
        return $this->belongsToMany('App\Models\Tag', 'item_tag', 'item_id');
    }

    public function itemTags()
    {
        return $this->belongsToMany(Tag::class, 'item_tag', 'item_id', 'tag_id');
    }

    public function sizeTag()
    {
        return $this->belongsTo('App\Models\Tag', 'size');
    }

    public function group()
    {
        return $this->belongsTo('App\Models\ItemGroup', 'group_id');
    }

    public function getSize()
    {
        return ! empty($this->sizeTag) ? $this->sizeTag->code : '';
    }

    public function getDisc()
    {
        // brand
        $disc = 0;
        switch ($this->brand) {
            case Item::BRAND_CB: $disc = 50;
                break;
            case Item::BRAND_CC:
                if (substr($this->pcode, 3, 1) == '0') {
                    $disc = 30;
                }
                break;
            default:
                break;
        }

        return $disc;
    }

    public function getImageUrl()
    {
        if ($this->type == Item::TYPE_ITEM && $this->group_id > 0) {
            return self::getImagePath($this->group_id);
        }

        return self::getImagePath($this->id);
    }

    public function getUploadPath()
    {
        $folder = str_pad(substr($this->id, -2), 2, '0', STR_PAD_LEFT);

        return LocalHelper::$var['item_image_path'].$folder;
    }

    public static function getImagePath($id)
    {
        $folder = str_pad(substr($id, -2), 2, '0', STR_PAD_LEFT);

        return 'https://cdn.corenationactive.com'.LocalHelper::$var['item_image_url'].$folder.'/'.$id.'.jpg';
    }

    public function getItemImagePathAttribute()
    {

        $idg = $this->group_id;

        $folder = str_pad(substr($idg, -2), 2, '0', STR_PAD_LEFT);

        $imageUrl = env('CDN_URL', '/laragon/www/core-nation/public/asset/').$folder.'/'.$idg.'.jpg';
        $imagePath = env('CDN_PATH', '/laragon/www/core-nation/public/asset/').$folder.'/'.$idg.'.jpg';

        return file_exists($imagePath)
        ? $imageUrl
        : asset('img/noimg.jpg');

        //  // Check if the file exists using Laravel's Storage
        // if (Storage::exists($imagePath)) {
        // 	// If it exists, construct the CDN URL or storage URL
        // 	return $imageUrl;
        // }

        // // If the file does not exist, return the default image URL
        // return asset('img/noimg.jpg');

    }

    public function getLancarImagePathAttribute()
    {

        $imageUrl = env('CDN_URL', '/laragon/www/core-nation/public/asset/').$this->id.'.jpg';
        $imagePath = env('CDN_PATH', '/laragon/www/core-nation/public/asset/').$this->id.'.jpg';

        return file_exists($imagePath)
        ? $imageUrl
        : asset('img/noimg.jpg');
    }

    public function printDescription2()
    {
        if ($this->type == Item::TYPE_ITEM) {
            return $this->group->description2 ?? '';
        }

        return $this->description2 ?? '';
    }

    public static function getDetailUrl($id, $type)
    {
        switch ($type) {
            case self::TYPE_ASSET_LANCAR: $action = 'asetLancar.detail';
                break;
            case self::TYPE_ASSET_TETAP: $action = 'item.detail';
                break;
            default: $action = 'item.detail';
                break;
        }

        return Route($action, $id);
    }

    public function getLink()
    {
        switch ($this->type) {
            case self::TYPE_ASSET_LANCAR: $action = 'asetLancar.detail';
                break;
            case self::TYPE_ASSET_TETAP: $action = 'item.detail';
                break;
            default: $action = 'item.detail';
                break;
        }

        return Route($action, $this->id);
    }
}
