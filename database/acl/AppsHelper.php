<?php

namespace App\Helpers;

use App\Models\Transaction;

class AppsHelper
{
    //App ids
	const HOME = 0;
	const USERS = 1;
	const SETTINGS = 2;
	const CUSTOMERS = 3;
	const SUPPLIERS = 4;
	const WAREHOUSES = 5;
	const BANKACCOUNTS = 6;
	const VWAREHOUSES = 7;
	const ITEMS = 8;
	const ASSET_LANCAR = 9;
	const ASSET_TETAP = 10;
	const TRANSACTIONS = 11;
	const TAGS = 12;
	const LOCATIONS = 14;
	const DELETED = 15;
	const ACCOUNTS = 16;
	const REPORTS = 17;
	const PERSONNELS = 18;
	const VACCOUNTS = 19;
	const RESELLERS = 20;
	const OPERATION = 21;
	const PELANGGARAN = 24;
	const CUTI = 25;
	const GAJI = 26;
	const BROADCAST = 28;
	const HASHTAG = 30;
	const NOTIFICATION = 31;
	const VITEM = 32;

	//functionalities
	const HIDE = 501;
	const PRINTER = 502;
	const TRANSACTION_FILTERS = 503;
	const SWITCHER = 504;

	//cronner, not in ACL
	const TRACK_INVOICE = 801;
	const TRACK_HASHTAG = 802;

	//add ons
	const PRODUCTIONS = 201; //OLD, DELETE
//	const SETORAN = 202;
	const BORONGAN = 203;
	const APRODUCTIONS = 204;
	const CONTRIBUTORS = 206;

	//NEW PRODUKSI
	const PRODUKSI = 205;
	const SETORAN = 207;

	//EXTERNAL APPS
	const SPG = 308;

	public static function init() {}

	public static $list = array(
		//apps
		self::HOME => '',
		self::USERS => 'users',
		self::SETTINGS => 'settings',
		self::CUSTOMERS => 'customers',
		self::SUPPLIERS => 'suppliers',
		self::WAREHOUSES => 'warehouses',
		self::BANKACCOUNTS => 'bank-accounts',
		self::VWAREHOUSES => 'vwarehouses',
		self::ITEMS => 'items',
		self::VITEM => 'vitems',
		self::ASSET_LANCAR => 'asset-lancar',
		self::ASSET_TETAP => 'asset-tetap',
		self::TRANSACTIONS => 'transactions',
		self::TAGS => 'tags',
		self::LOCATIONS => 'locations',
		self::DELETED => 'deleted',
		self::ACCOUNTS => 'accounts',
		self::REPORTS => 'reports',
		self::PERSONNELS => 'personnels',
		self::VACCOUNTS => 'vaccounts',
		self::RESELLERS => 'resellers',
		self::OPERATION => 'operations',
		self::PELANGGARAN => 'pelanggaran',
		self::CUTI => 'cuti',
		self::GAJI => 'gaji',
		self::BROADCAST => 'broadcast',
		self::HASHTAG => 'hash',
		self::NOTIFICATION => 'notification',

		//functions
		self::HIDE => 'hide',
		self::PRINTER => 'print',
		self::TRANSACTION_FILTERS => 'transaction-filters',
		self::SWITCHER => 'switch',

		//add-ons
		self::BORONGAN => 'borongan',
		self::APRODUCTIONS => 'aproduksi',
		self::PRODUKSI => 'produksi',
		self::CONTRIBUTORS => 'contibutors',
		self::SETORAN => 'setoran',

		//external
		self::SPG => 'spg',
	);
	public static $actions = array(
		self::USERS => array(
			'index' => 'List of users',
			'create' => 'Create a user',
			'edit' => 'Edit a user',
			'ban' => 'Ban a user',
			'unban' => 'Unban a user',
			'roles' => 'view roles list',
			'create-role' => 'Creates a role',
			'edit-role' => 'Edits a role',
			'acl' => 'Edits Access Control List',
		),
		self::SETTINGS => array(
			'index' => 'View app settings',
		),
		self::CUSTOMERS => array(
			'index' => 'List of customers',
			'create' => 'Create a Customer',
			'edit' => 'Edit a customer',
			'detail' => 'detail',
			'transactions' => 'view transactions',
			'stat' => 'view stats',
			'items' => 'view items',
			'search-item' => 'view search-item',
			'delete' => 'delete',
			'restore' => 'restore',
			'sales' => 'check item sales',
		),
		self::SUPPLIERS => array(
			'index' => 'List of suppliers',
			'create' => 'Create a supplier',
			'edit' => 'Edit a supplier',
			'detail' => 'detail',
			'transactions' => 'view transactions',
			'stat' => 'view stats',
			'items' => 'view items',
			'search-item' => 'view search-item',
			'delete' => 'delete',
			'restore' => 'restore',
		),
		self::WAREHOUSES => array(
			'index' => 'List of warehouses',
			'create' => 'Create a warehouse',
			'edit' => 'Edit a warehouse',
			'detail' => 'detail',
			'transactions' => 'view transactions',
			'stat' => 'view stats',
			'items' => 'view items',
			'search-item' => 'view search-item',
			'delete' => 'delete',
			'restore' => 'restore',
			'sales' => 'check item sales',
		),
		self::BANKACCOUNTS => array(
			'index' => 'List of accounts',
			'create' => 'Create a accounts',
			'edit' => 'Edit a accountss',
			'detail' => 'detail',
			'transactions' => 'view transactions',
			'stat' => 'view stats',
			'items' => 'view items',
			'search-item' => 'view search-item',
			'delete' => 'delete',
			'restore' => 'restore',
		),
		self::VWAREHOUSES => array(
			'index' => 'List of virtual warehouse',
			'create' => 'Create a virtual warehouse',
			'edit' => 'Edit a virtual warehouse',
			'detail' => 'detail',
			'transactions' => 'view transactions',
			'stat' => 'view stats',
			'items' => 'view items',
			'search-item' => 'view search-item',
			'delete' => 'delete',
			'restore' => 'restore',
		),
		self::VACCOUNTS => array(
			'index' => 'List of virtual accounts',
			'create' => 'Create a virtual account',
			'edit' => 'Edit a virtual account',
			'detail' => 'virtual account detail',
			'transactions' => 'view transactions',
			'stat' => 'view stats',
			'items' => 'view items',
			'search-item' => 'view search-item',
			'delete' => 'delete',
			'restore' => 'restore',
		),
		self::ITEMS => array(
			'index' => 'List of items',
			'create' => 'Create an item',
			'edit' => 'Edit and item',
			'detail' => 'detail',
			'price' => 'update price',
			'sell-stats' => 'see sell stats',
			'use-stats' => 'see use stats',
			'transactions' => 'see transactions',
			'group' => 'see group',
			'see-tags' => 'see tags',
		),
		self::ASSET_LANCAR => array(
			'index' => 'List of asset lancar',
			'create' => 'Create an asset lancar',
			'edit' => 'Edit and asset lancar',
			'transactions' => 'see transactions',
			'detail' => 'detail',
			'see-cost' => 'see cost',
		),
		self::ASSET_TETAP => array(
			'index' => 'List of asset tetap',
			'create' => 'Create an asset tetap',
			'edit' => 'Edit and asset tetap',
			'transactions' => 'see transactions',
			'detail' => 'detail',
		),
		self::VITEM => array(
			'index' => 'List of virtual item',
			'create' => 'Create an virtual item',
			'edit' => 'Edit and virtual item',
			'detail' => 'detail',
		),
		self::TRANSACTIONS => array(
			'index' => 'Transaction list',
			'buy' => 'New buy',
			'sell' => 'New sell',
			'detail' => 'View transaction detail',
			'transfer' => 'Transfer between accounts',
			'cash-in' => 'New cash in',
			'cash-out' => 'New cash out',
			'adjust' => 'new adjust',
			'return' => 'Return',
			'return-supplier' => 'Return Supplier',
			'move' => 'Move items',
			'use' => 'Use items',
			'delete' => 'Delete a transaction',
			'detail' => 'detail',
			'image' => 'edit image',
			'sell-batch' => 'sell-batch',
			'move-batch' => 'move-batch',
		),
		self::TAGS => array(
			'index' => 'View tags list',
			'create' => 'Create a tag',
			'edit' => 'Edit a tag',
			'detail' => 'detail',
		),
		self::LOCATIONS => array(
			'index' => 'View locations',
			'create' => 'Create location',
			'edit' => 'Edit location',
			'assign' => 'Assign to location',
			'dismiss' => 'Dismiss from location',
			'settings' => 'edit location settings',
		),
		self::DELETED => array(
			'index' => 'List of deleted crap',
			'detail' => 'view detailed deleted crap',
		),
		self::ACCOUNTS => array(
			'index' => 'List of journal types',
			'detail' => 'view',
			'edit' => 'edit journal type',
			'create' => 'create journal type',
		),
		self::REPORTS => array(
			'profit-loss' => 'view profit loss',
			'revenue' => 'view revenue breakdown',
			'aspc' => 'aspc',
			'cash-flow' => 'view cash flow',
			'balance' => 'view balance reports',
			'cash' => 'nett cash',
		),
		self::PERSONNELS => array(
			'index' => 'personnel list',
			'edit' => 'edit personnel',
			'create' => 'create personnel',
			'edit-cuti' => 'edit personnel cuti',
			'delete' => 'delete personnel',
			'restore' => 'restore personnel',
			'gpu' => 'view gpu list',
			'edit-gpu' => 'edit gpu',
			'private-gpu' => 'view private gpu',
		),
		self::RESELLERS => array(
			'index' => 'List of resellers',
			'create' => 'Create a reseller',
			'edit' => 'Edit a reseller',
			'detail' => 'detail',
			'transactions' => 'view transactions',
			'stat' => 'view stats',
			'items' => 'view items',
			'sales' => 'view sales',
			'search-item' => 'view search-item',
			'delete' => 'delete',
			'restore' => 'restore',
			'portal' => 'create portal',
			'unportal' => 'delete portal',
			'addwarehouse' => 'add warehouse to portal',
		),
		self::OPERATION => array(
			'index' => 'operations list',
			'create' => 'create operations',
			'edit' => 'edit operations',
			'detail' => 'operation detail',
			'accounts' => 'accounts list',
			'create-account' => 'create account',
			'edit-account' => 'edit account',
			'account-detail' => 'account details',
			'account-hash' => 'account hashtags',
		),
		self::PELANGGARAN => array(
			'index' => 'pelanggaran list',
			'add' => 'add pelanggaran',
			'delete' => 'edit pelanggaran',
		),
		self::CUTI => array(
			'index' => 'cuti list',
			'add' => 'add cuti',
			'edit' => 'edit cuti',
			'settings' => 'cuti settings'
		),
		self::GAJI => array(
			'index' => 'gaji list',
			'generate' => 'generate gaji',
			'edit' => 'edit gaji',
//			'settings' => 'gaji settings',
//			'private-gpu' => 'can view private gpu',
			'private' => 'view private gaji',
		),
		self::BROADCAST => array(
			'index' => 'broadcast list',
			'create' => 'create broadcast',
			'edit' => 'edit broadcast',
		),
		self::HASHTAG => array(
			'index' => 'hashtags list',
			'stat' => 'view hashtag stat',
			'transactions' => 'view hashtag transaction',
		),
		self::NOTIFICATION => array(
			'index' => 'front page notification',
			'due-reseller' => 'view due payments for resellers',
			'due-suplier' => 'view due payments for suppliers',
		),

		//functions
		self::HIDE => array(
			'balance' => 'Hides balance from transactions list',
			'smart-home' => 'Hides smart home, displays shortcuts instead',
		),
		self::PRINTER => array(
			'transaction' => 'can print transaction',
			'c-items' => 'can print items in addr book',
			'produksi' => 'can print produksi',
		),
		self::TRANSACTION_FILTERS => array(
			Transaction::TYPE_SELL => 'only show sell in transaction list',
			Transaction::TYPE_BUY => 'only show buy in transaction list',
			Transaction::TYPE_RETURN => 'Return',
			Transaction::TYPE_MOVE => 'Move Items',
			Transaction::TYPE_TRANSFER => 'Transfer',
			Transaction::TYPE_CASH_OUT => 'Cash Out',
			Transaction::TYPE_USE => 'Use Items',
			Transaction::TYPE_CASH_IN => 'Cash In',
			Transaction::TYPE_ADJUST => 'Adjust',
			Transaction::TYPE_PRODUCTION => 'Production',
			Transaction::TYPE_RETURN_SUPPLIER => 'Return Supplier',
		),
		self::SWITCHER => array(
			'addr-book' => 'switch an addr book contact to a different type',
			'stuff' => 'switch a stuff to another type',
		),

		self::CONTRIBUTORS => array(
			'index' => 'view contributors',
		),
		self::BORONGAN => array(
			'index' => 'daftar borongan',
			'add' => 'bikin borongan',
			'load' => 'load borongan',
			'detail' => 'borongan detail',
		),
		self::APRODUCTIONS => array(
			'index' => 'daftar arsip produksi',
		),
		self::PRODUKSI => array(
			'index' => 'produksi list',
			'create-produksi' => 'create produksi',
			'edit' => 'edit produksi',
			'jahit-list' => 'jahit list',
			'create-jahit' => 'create jahit',
			'edit-jahit' => 'edit jahit',
			'delete-jahit' => 'delete jahit',
			'restore-jahit' => 'restore jahit',
			'potong-list' => 'potong list',
			'create-potong' => 'create potong',
			'edit-potong' => 'edit potong',
			'delete-potong' => 'delete potong',
			'restore-potong' => 'restore potong',
			'setor' => 'setor',
			'save-row' => 'edit item',
			'ganti-jahit' => 'edit penjahit di produksi',
			'pisah-jahit' => 'pisah jahitan',
			'delete' => 'delete kitir',
		),
		self::SETORAN => array(
			'index' => 'produksi list',
			'gudang' => 'setor ke gudang',
			'edit-item' => 'edit item',
			'edit' => 'edit warna/deskripsi',
			'edit-jahit' => 'edit jahit',
			'edit-status' => 'edit status',
			'delete' => 'delete kitir',
		),

		//external
		self::SPG => array(
			'index' => 'spg list',
			'create' => 'create spg',
			'edit' => 'edit spg'
		),
	);

	//to avoid array debugging nightmare
	public static $notes = array(
		self::USERS => array(
			'index' => 'Needed for create and edit users',
			'create' => 'Requires user index',
			'edit' => 'Requires user index',
			'ban' => 'Banned user cannot login',
			'unban' => 'Unban a user',
			'create-role' => 'Creates a role',
			'edit-role' => 'Edits a role',
			'acl' => 'Edits Access Control List',
		),
	);

}




