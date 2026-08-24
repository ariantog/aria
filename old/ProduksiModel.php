<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produksi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'prod_produksi';
	protected $fillable = ['temp_name', 'size_id', 'quantity', 'customer', 'warna', 'description', 'item_id'];
	protected $dates = ['deleted_at'];

	const STATUS_PRODUKSI = 0; //in produksi
	const STATUS_SETOR = 1; //in arsip
	const STATUS_BAYAR = 3; //bayar belum turun, bayar = 3, merah
	const STATUS_GUDANG = 5; //sudah turun, belum bayar, turun = 5, orange
	const STATUS_BOTH = 15; //sudah bayar, sudah turun, bayar x turun = 15, hijau

	public static $statuses = array(
		self::STATUS_SETOR => '-Bayar-Turun',
		self::STATUS_BAYAR => '+Bayar-Turun',
		self::STATUS_GUDANG => '-Bayar+Turun',
		self::STATUS_BOTH => '+Bayar+Turun',
	);

	public static $statusJSON = array(
		array('id' => 0, 'name' => 'All'),
		array('id' => self::STATUS_SETOR, 'name' => '-Bayar-Turun'),
		array('id' => self::STATUS_BAYAR, 'name' => '+Bayar-Turun'),
		array('id' => self::STATUS_GUDANG, 'name' => '-Bayar+Turun'),
		array('id' => self::STATUS_BOTH, 'name' => '+Bayar+Turun'),
	);

	public function printClass()
	{
		switch($this->status)
		{
			case self::STATUS_BAYAR: return 'danger'; break;
			case self::STATUS_GUDANG: return 'warning'; break;
			case self::STATUS_BOTH: return 'success'; break;
			default: return ''; break;
		}
	}

	public static $rules = array(
		'potong_date' => 'required',
		'potong_id' => 'required',
	);

	public static function table()
	{
		return 'prod_produksi';
	}

	public function transactionDetail()
	{
		return $this->belongsTo('App\Models\TransactionDetail', 'detail_id');
	}

	public function transaction()
	{
		return $this->belongsTo('App\Models\Transaction', 'transaction_id');
	}

	public function item()
	{
		return $this->belongsTo('App\Models\Item','item_id','id');
	}

	public function potong()
	{
		return $this->belongsTo('App\Models\Worker','potong_id');
	}

	public function jahit()
	{
		return $this->belongsTo('App\Models\Worker','jahit_id');
	}

	public function qc()
	{
		return $this->belongsTo('App\Models\Worker','qc_id');
	}

	public function size()
	{
		return $this->belongsTo('App\Models\Tag','size_id');
	}

	public function user()
	{
		return $this->belongsTo('App\Models\User','user_id');
	}

	public function serial()
	{
		return self::toSerial($this->id);
	}

	public function originalSerial()
	{
		if(!$this->original_id) return false;
		return self::toSerial($this->original_id);
	}

	public static function fromSerial($id)
	{
		return base_convert(trim($id),36,10);
	}

	public static function toSerial($id)
	{
		return strtoupper(base_convert($id,10,36));
	}

	// public function getEditLink()
	// {
	// 	if($this->status == Produksi::STATUS_SETOR)
	// 		return \URL::action('SetoranController@getEdit',array($this->id)); 
	// 	if($this->status == Produksi::STATUS_PRODUKSI)
    //       return \URL::action('ProduksiController@getEdit',array($this->id));
	// 	return \URL::action('SetoranController@getIndex',array($this->id)); ;
	// }

	public function setJahitDateSqlAttribute($value)
	{
		$this->dateToSQL('jahit_date',$value);
	}

	public function setSetorDateSqlAttribute($value)
	{
		$this->dateToSQL('setor_date',$value);
	}
}
