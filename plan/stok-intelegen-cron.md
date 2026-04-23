buat cron generet stok intelegence, buat 2 model 
model stok_report = [generet_at(datetime), type(cron/manual), generet_by(null, id user yang generet manual)]
model stock_data isi nya
{
    "id_stock_report: 1"
    "item_id": 86794,
    "item_name": "CPJ CX80040/01 M",
    "score": 0,
    "performance_key": "deadstock",
    "performance_level": "6. Deadstock (Mati)",
    "gap_days": 93,
    "current_warehouse_id": 1689,
    "current_warehouse_name": "PAMERAN - JKT",
    "current_warehouse_qty": 1,
    "current_warehouse_last_sale": "2025-03-03",
    "current_warehouse_days_ago": 415
    "best_performing_warehouse_name": "Central - GI",
    "best_performing_warehouse_last_sale": "2025-06-04",
    "best_performing_warehouse_days_ago": 322,
    "best_performing_warehouse_qty": -5,
    "best_performing_warehouse_id": 2628
},

generel logic sama dengan app\Http\Controllers\ReportController.php function stockIntelligence
jangan ubah logic pastikan sama konsiten.
data yang dismpan ada 1000 data dengan skor terbaik.

run dalam 1 menit sekali

jangan ubah/hapus data di database sangat di larang