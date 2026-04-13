<?php

namespace App\Helpers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemTag;
use App\Models\Tag;
use App\Models\WarehouseItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Laravel\Facades\Image;

class ItemsManagerHelper
{
    public static $_tags;

    public static $_json;

    public function createItems($input, $inputTags, $file)
    {
        if (! isset($input->pcode) || empty($input->pcode)) {
            throw new \Exception('pcode is required');
        }

        if ($input->type == Item::TYPE_ITEM) {
            if (! preg_match("/([a-zA-Z]{2})([0-9]{5})\/([0-9]{2})/", $input->pcode)) {
                throw new \Exception('pcode salah format');
            }
        }

        static::loadTags();

        // find the tags
        $inputTags = $inputTags;
        $inputTags = static::sortTags($inputTags);

        // dd($inputTags);
        // create group
        $group = ItemGroup::where('name', '=', $input->pcode)->first();
        if ($input->type == Item::TYPE_ITEM) {
            if (! $group) {
                $group = new ItemGroup;
            }

            $group->name = $input->pcode;
            $code = explode('/', $input->pcode);
            if (isset($code[0])) {
                $group->master = $code[0];
            }
            if (isset($code[1])) {
                $group->variant = $code[1];
            }

            if (isset($input->description)) {
                $group->description = strtoupper($input->description);
            }
            if (isset($input->description2)) {
                $group->description2 = strtoupper($input->description2);
            }
            if (! isset($input->alias)) {
                $input->alias = '';
            }
            $group->alias = strtoupper($input->alias);
            if (! $group->save()) {
                return $this->error($group->getErrors());
            }
        }

        // then create the items
        $total = 0;
        foreach ($inputTags['types'] as $key => $type_id) {
            foreach ($inputTags['sizes'] as $key => $size_id) {
                if (! $item = $this->createCrystalItem($group, $input, $inputTags, $type_id, $size_id)) {
                    return $this->error('error creating item');
                }

                $total++;
            }
        }
        $this->saveImage($group, $file);

        if ($total < 1) {
            throw new \Exception('TYPE + SIZE harus ada');
        }

        return true;
    }

    protected function saveImage($group, $file)
    {
        if (empty($file)) {
            return;
        }

        $manager = new ImageManager(new Driver); // atau 'imagick' jika diinginkan

        // Ambil file gambar yang diunggah
        $image = $file;

        if ($group) {
            $folder = str_pad(substr($group->id, -2), 2, '0', STR_PAD_LEFT);
            $pathFile = $folder.'/'.$group->id.'.jpg';

        } else {
            $folder = '';
            $pathFile = $group->id.'.jpg';
        }

        $filename = $pathFile;

        // Buat instance gambar dari file yang diunggah
        // $img = $manager->make($image->getRealPath());
        $img = $manager->read($image->getRealPath());

        // Tetapkan batas maksimal dimensi tanpa mengubah rasio asli
        // Dapatkan ukuran asli

        $maxSize = 1000;
        $originalWidth = $img->width();
        $originalHeight = $img->height();

        if ($originalWidth > $originalHeight) {
            $scale = $maxSize / $originalWidth; // Skala berdasarkan lebar
        } else {
            $scale = $maxSize / $originalHeight; // Skala berdasarkan tinggi
        }

        // Hitung dimensi baru
        $maxWidth = round($originalWidth * $scale);
        $maxHeight = round($originalHeight * $scale);

        // Hitung ukuran baru (50% lebih kecil)
        // $maxWidth = $originalWidth * 0.5;
        // $maxHeight = $originalHeight * 0.5;
        if ($img->width() > $maxWidth || $img->height() > $maxHeight) {
            $img->resize($maxWidth, $maxHeight, function ($constraint) {
                $constraint->aspectRatio(); // Menjaga rasio asli
                $constraint->upsize();      // Mencegah gambar menjadi lebih besar dari ukuran aslinya
            });
        }

        // Tentukan kualitas awal dan path tujuan
        $quality = 85; // Mulai dengan kualitas 85%
        $path = env('CDN_PATH', '/laragon/www/core-nation/public/asset/');
        $filePath = $path.$filename;

        // Buat direktori jika belum ada
        if (! File::exists($path.$folder)) {
            File::makeDirectory($path.$folder, 0755, true); // Membuat direktori dengan izin 755
        }

        // dd($filePath);
        // Simpan gambar dan kompresi hingga ukurannya di bawah 100KB
        do {
            // Simpan gambar ke path dengan kualitas yang ditentukan
            $img->save($filePath, $quality);

            // Hitung ukuran file
            $size = filesize($filePath);
            $quality -= 5; // Kurangi kualitas jika ukuran file masih lebih dari 100KB
        } while ($size > 100 * 1024 && $quality > 10); // Teruskan hingga ukuran file di bawah 100KB

        // //save image using group id
        // $path = $group->getUploadPath();
        // $filename = $group->id.'.jpg';
        // //check for file exist
        // if(file_exists($path.'/'.$filename)) {
        // 	chmod($path.'/'.$filename,0755);
        // 	unlink($path.'/'.$filename);
        // }
        // $file = $file->move($path, $filename);

        // Image::read($file->getPathName())->resize(588, null, function($constraint) {
        // 	$constraint->aspectRatio();
        // })->save();
    }

    // to avoid accidental creation
    public function createItem($input, $tags, $file = null, $item = false)
    {
        throw new \Exception('function disabled');
    }

    protected function sortTags($tags)
    {
        if (! is_array($tags)) {
            return ['types' => [], 'sizes' => [], 'jahit' => []];
        }

        // get the item types
        // $types = array_filter(array_keys(array_intersect_key($tags,static::$_tags[Tag::TYPE_TYPE])));
        if (array_key_exists(Tag::TYPE_WARNA, $tags)) {
            $warna = $tags[Tag::TYPE_WARNA];
        } else {
            $warna = [];
        }

        if (array_key_exists(Tag::TYPE_TYPE, $tags)) {
            $types = $tags[Tag::TYPE_TYPE];
        } else {
            $types = [];
        }
        // get the item sizes
        // $sizes = array_filter(array_keys(array_intersect_key($tags,static::$_tags[Tag::TYPE_SIZE])));

        if (array_key_exists(Tag::TYPE_SIZE, $tags)) {
            $sizes = $tags[Tag::TYPE_SIZE];
            if (empty($sizes)) {
                $sizes = [0 => 0];
            }
        } else {
            $sizes = [];
        }
        // to keep the loop going in create

        // $jahit = array_filter(array_keys(array_intersect_key($tags,static::$_tags[Tag::TYPE_JAHIT])));
        $jahit = $tags[Tag::TYPE_JAHIT];
        if (is_array($jahit) && count($jahit) > 0) {
            $jahit = $jahit[0];
        }

        return [
            'warna' => $warna,
            'types' => $types,
            'sizes' => $sizes,
            'jahit' => $jahit,
        ];
    }

    protected function createCrystalItem($group, $input, $tags, $type_id, $size_id, $item = false, $action = 'store')
    {
        if (! $item) {
            $item = new Item;
        }
        $item->pcode = strtoupper(trim($input->pcode));
        $item->price = $input->price;
        $item->description = $input->description;
        $item->cost = $input->cost ?? '';
        $item->description = $input->description ?? '';
        $item->description2 = $input->description2 ?? '';
        $item->save();
        $item->type = $input->type;
        if ($item->type = Item::TYPE_ASSET_LANCAR) {
            $item->name = strtoupper($input->name); // utk asset lancar
            $item->code = $item->pcode; // untuk edit
        }

        if ($group) {
            $item->group_id = $group->id;
            $item->variant = $group->variant;
        }

        // combine the tags
        if ($action == 'store') {
            $tag_ids = array_filter([$tags['jahit'], $type_id, $size_id, $tags['warna'][0]]);
        } else {
            $tag_ids = array_filter([$tags['jahit'][0], $type_id, $size_id, $tags['warna'][0]]);
        }

        // dd(implode(',',$tag_ids));
        asort($tag_ids);
        $item->tag_ids = implode(',', $tag_ids);

        // 1. generate the item code
        if ($item->type == Item::TYPE_ITEM) {
            $item->code = static::$_tags[Tag::TYPE_TYPE][$type_id]->code.str_replace('/', '', $item->pcode); // add type
            $item->code = $item->code.static::$_tags[Tag::TYPE_SIZE][$size_id]->code;
            $item->code = strtoupper($item->code);
            $item->name = static::$_tags[Tag::TYPE_TYPE][$type_id]->code.' '.$item->pcode.' '.static::$_tags[Tag::TYPE_SIZE][$size_id]->name;
        }

        // NEW: catch for contributor data
        $item->genre = $type_id;
        $item->size = $size_id;
        $brand = strtoupper(substr($item->pcode, 0, 2));
        // special case for CX
        switch ($brand) {
            case 'CX':
                $brand = strtoupper(substr($item->pcode, 0, 3));
                break;
        }
        $brand = array_search($brand, Item::$brands);
        if ($brand) {
            $item->brand = $brand;
        } else {
            $item->brand = 0;
        }
        if (! $item->save()) {
            return $this->error($item->getErrors());
        }

        // sync
        $item->tags()->sync($tag_ids);

        return $item;
    }

    public function updateItem($id, $input, $inputTags, $file = null)
    {
        if (! isset($input->pcode) || empty($input->pcode)) {
            throw new \Exception('pcode is required');
        }

        if ($input->type == Item::TYPE_ITEM) {
            if (! preg_match("/([a-zA-Z]{2})([0-9]{5})\/([0-9]{2})/", $input->pcode)) {
                throw new \Exception('pcode salah format');
            }
        }

        static::loadTags();
        $inputTags = $inputTags;

        $item = Item::with('group')->findOrFail($id);

        $arrayTag = explode(',', $item->tag_ids);

        $typeTag = Tag::whereIn('id', $arrayTag)->where('type', Tag::TYPE_TYPE)->pluck('id')->toArray();
        $typeSize = Tag::whereIn('id', $arrayTag)->where('type', Tag::TYPE_SIZE)->pluck('id')->toArray();

        $inputTags = Arr::add($inputTags, 'types', $typeTag);
        $inputTags = Arr::add($inputTags, 'sizes', $typeSize);
        $inputTags = Arr::add($inputTags, 'warna', $inputTags['warna'][0]);

        $type_id = isset($inputTags['types'][0]) ? $inputTags['types'][0] : 0;
        $size_id = isset($inputTags['sizes'][0]) ? $inputTags['sizes'][0] : 0;
        $jahit_id = isset($inputTags['jahit']) ? $inputTags['jahit'] : 0;

        $old_tags = explode(',', $item->tag_ids);
        $group_id = $item->group_id;

        // then create the items
        if (! $item = $this->createCrystalItem($item->group, $input, $inputTags, $type_id, $size_id, $item, 'update')) {
            return $this->error('error creating item');
        }

        // update group
        if ($item->type == Item::TYPE_ITEM) {
            $group = ItemGroup::findOrFail($item->group_id);
            if ($group && $item->type) {
                if (isset($input->description)) {
                    $group->description = strtoupper($input->description);
                }
            }
            if (isset($input->description2)) {
                $group->description2 = strtoupper($input->description2);
            }
            if (! isset($input->alias)) {
                $input->alias = '';
            }
            $group->alias = strtoupper($input->alias);
            if (! $group->save()) {
                return $this->error($group->getErrors());
            }
        }

        if (! empty($file)) {
            $this->saveImage($item->group, $file);
        }

        // NEXT, update jahit for other items
        // 1. find if jahit has been modified
        // SKIP IF ASET LANCAR
        if ($item->type == Item::TYPE_ITEM && ! in_array($jahit_id, $old_tags)) { // not in array, modified
            // 2. find other items in the same group, with the same type, but different size
            $itemTable = Item::table();
            $updates = Item::with('tags')->where('group_id', '=', $group_id)->where('genre', '=', $type_id)->get();

            foreach ($updates as $update) {
                // skip same id
                if ($update->id == $item->id) {
                    continue;
                }

                $new_tags = array_filter([$inputTags['jahit'][0], $update->genre, $update->size, $inputTags['warna'][0]]);
                $update->tag_ids = implode(',', $new_tags);
                $update->save();
                $update->tags()->sync($new_tags);
            }
        }

        return $item;
    }

    // does not support multiple type + size
    public function updatePrice($price, $inputTags)
    {
        if (empty($price)) {
            return $this->error('price is required', 'code');
        }

        static::loadTags();

        // find the tags
        $total = 0;
        $inputTags = static::sortTags($inputTags);

        $it = Item::table();
        $itt = ItemTag::table();
        foreach ($inputTags['types'] as $key => $type_id) {
            foreach ($inputTags['sizes'] as $key => $size_id) {
                $tags = $inputTags;
                $tag_ids = array_filter([$tags['jahit'], $type_id, $size_id]); // check
                asort($tag_ids);

                $ids = DB::table($it)
                    ->select([$it.'.id', DB::raw("COUNT(DISTINCT $itt.tag_id) as counter")])->join($itt, $itt.'.item_id', '=', $it.'.id')
                    ->whereIn($itt.'.tag_id', $tag_ids)
                    ->groupBy($it.'.id')
                    ->having('counter', '=', count($tag_ids))->lists($it.'.id');
                $total_ids = count($ids);
                $total += $total_ids;
                if ($total_ids > 0) {
                    $affected = DB::table($it)->whereIn('id', $ids)->update(['price' => DB::raw('price + '.$price)]);
                }
            }
        }

        return $total;
    }

    public static function loadTags($itemType = null)
    {
        Cache::forget('item_tags_'.($itemType ?? '0'));
        // static::$_tags = Cache::remember('item_tags_' . ($itemType ?? '0'), 300, function () {
        // 	$tags = array();

        // 	//initialize the tag types
        // 	foreach(Tag::$types as $type => $val)
        // 	{
        // 		$tags[$type] = array();
        // 	}

        // 	$tags_db = Tag::orderBy('type')->orderBy('code','asc')->get();
        // 	foreach($tags_db as $t)
        // 	{
        // 		$tags[$t->type][$t->id] = $t;
        // 	}

        // 	return $tags;
        // });

        static::$_tags = Cache::remember('item_tags_'.($itemType ?? '0'), 300, function () use ($itemType) {
            $tags = [];

            // initialize tag types
            foreach (Tag::$types as $type => $val) {
                $tags[$type] = [];
            }

            $query = Tag::orderBy('type')->orderBy('code', 'asc');

            // filter by model type (e.g., App\Models\Item or App\Models\Aset)
            if ($itemType) {
                $query->whereIn('item_type', [0, $itemType]); // Sesuaikan nama kolom jika beda
            }

            $tags_db = $query->get();

            foreach ($tags_db as $t) {
                $tags[$t->type][$t->id] = $t;
            }

            return $tags;
        });

        return static::$_tags;
    }

    // TODO: clean this up, make a universal function
    public static function loadTagsJSON($itemType, $types)
    {

        if (empty(static::$_tags)) {
            static::loadTags($itemType);
        }
        Cache::forget('item_tags_json_'.($itemType ?? '0'));
        static::$_json = Cache::remember('item_tags_json_'.($itemType ?? '0'), 300, function () use ($types, $itemType) {
            $data = [];

            $count = 0;

            if ($itemType == Item::TYPE_ASSET_LANCAR) {

                foreach ($types as $type => $val) {
                    $data[$count] = [];
                    $data[$count]['name_input'] = $val['name_input'] ?? '';
                    $data[$count]['name'] = $val['name'] ?? '';
                    $data[$count]['type_id'] = $type;
                    $data[$count]['data'] = [];
                    foreach (static::$_tags[$type] as $value) {
                        $data[$count]['data'][] = $value->toArray();
                    }

                    $count++;
                }

            } else {

                foreach ($types as $type => $val) {
                    $data[$count] = [];
                    $data[$count]['name'] = $val;
                    $data[$count]['type_id'] = $type;
                    $data[$count]['data'] = [];
                    foreach (static::$_tags[$type] as $value) {
                        $data[$count]['data'][] = $value->toArray();
                    }

                    $count++;
                }

            }

            return $data;
        });

        return static::$_json;
    }

    // item can be eloquent or std object instance
    public static function add($warehouse_id, $item, $quantity)
    {
        if ($item->type == Item::TYPE_SERVICE) {
            return true;
        }

        if (! $wi = WarehouseItem::where('warehouse_id', '=', $warehouse_id)->where('item_id', '=', $item->id)->lockForUpdate()->first()) {
            $wi = WarehouseItem::create(['warehouse_id' => $warehouse_id, 'item_id' => $item->id, 'quantity' => 0]);
        }

        // dd($wi);

        $wi->quantity += $quantity;

        if (! $wi->save()) {
            throw new \Exception($wi->errors->first());
        }

        return $wi;
    }

    public static function deduct($warehouse_id, $item, $quantity, $can_minus = false)
    {
        if ($item->type == Item::TYPE_SERVICE) {
            return true;
        }

        if (! $wi = WarehouseItem::where('warehouse_id', '=', $warehouse_id)->where('item_id', '=', $item->id)->lockForUpdate()->first()) {
            $wi = WarehouseItem::create(['warehouse_id' => $warehouse_id, 'item_id' => $item->id, 'quantity' => 0]);
        }

        // dd($wi);

        if (! $can_minus && ($wi->quantity - $quantity) < 0) { // check if minus is allowed
            throw new \Exception("{$item->code} cuma ada {$wi->quantity}, mau diambil {$quantity}");
        }

        $wi->quantity -= $quantity;
        if (! $wi->save()) {
            throw new \Exception($wi->errors->first());
        }

        return $wi;
    }

    public static function toArray($items)
    {
        $response = [];
        if ($items instanceof \Illuminate\Pagination\Paginator) {
            $response['currentPage'] = $items->getCurrentPage();
            $response['lastPage'] = $items->getLastPage();
        }
        $response['data'] = [];
        foreach ($items as $key => $detail) {
            $response['data'][$key]['image'] = $detail->getImageUrl();
            $response['data'][$key]['link'] = $detail->getDetailLink();
            $response['data'][$key]['edit_link'] = $detail->getEditLink();
            $response['data'][$key]['name'] = $detail->name;
            $response['data'][$key]['id'] = $detail->id;
            $response['data'][$key]['code'] = $detail->code;
            $response['data'][$key]['pcode'] = $detail->pcode;
            if ($detail->type == Item::TYPE_ITEM) {
                if (! $detail->group) {
                    pre($detail->group);
                    exit;
                }
                $response['data'][$key]['description'] = $detail->group->description;
                $response['data'][$key]['description2'] = $detail->group->description2;
                $response['data'][$key]['alias'] = $detail->group->alias;
            } else {
                $response['data'][$key]['description'] = $detail->description;
                $response['data'][$key]['description2'] = $detail->description2;
                $response['data'][$key]['alias'] = $detail->alias;
            }
            $response['data'][$key]['price'] = $detail->price;
            $response['data'][$key]['edit'] = $detail->getEditLink();
            $response['data'][$key]['total_quantity'] = 0;
            $response['data'][$key]['total_quantity'] = self::findItemInWarehouse($detail->id);

            if ($detail->depreciation) {
                $response['data'][$key]['d_date'] = $detail->depreciation->buy_date;
                $response['data'][$key]['d_monthly'] = $detail->depreciation->monthlyDepreciation();
                $response['data'][$key]['d_value'] = $detail->depreciation->calcValue();
            }
        }

        return $response;
    }

    public static function findItemInWarehouse($itemId)
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

    public static function findGroupInWarehouse($groupId)
    {
        $total = DB::table('items')->select([
            'items.group_id',
            DB::raw('SUM(warehouse_item.quantity) as total_quantity'),
        ])->where('items.group_id', '=', $groupId)->join('warehouse_item', 'items.id', '=', 'warehouse_item.item_id')->leftJoin('customers', 'customers.id', '=', 'warehouse_item.warehouse_id')->where('customers.type', '=', Customer::TYPE_WAREHOUSE)->where(function ($query) {
            $query->where('customers.deleted_at', '=', null)->orWhere('customers.deleted_at', '=', '0000-00-00 00:00:00');
        })->first();

        if (! $total) {
            return 0;
        }

        return $total->total_quantity;
    }
}
