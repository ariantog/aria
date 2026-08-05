-- Item identity migration helpers (run in phpMyAdmin on production MySQL).
-- Review results with SELECT before DELETE/UPDATE. Take a backup first.

-- ---------------------------------------------------------------------------
-- 1) Find items never used in warehouse or transactions, created > 1 year ago
-- ---------------------------------------------------------------------------
SELECT i.id, i.code, i.name, i.pcode, i.type, i.created_at
FROM items i
WHERE i.deleted_at IS NULL
  AND i.created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
  AND NOT EXISTS (
    SELECT 1 FROM warehouse_items wi WHERE wi.item_id = i.id AND wi.quantity <> 0
  )
  AND NOT EXISTS (
    SELECT 1 FROM transaction_details td WHERE td.item_id = i.id
  )
ORDER BY i.id;

-- Soft-delete unused items (uncomment after reviewing the SELECT above):
-- UPDATE items i
-- SET i.deleted_at = NOW()
-- WHERE i.deleted_at IS NULL
--   AND i.created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
--   AND NOT EXISTS (SELECT 1 FROM warehouse_items wi WHERE wi.item_id = i.id AND wi.quantity <> 0)
--   AND NOT EXISTS (SELECT 1 FROM transaction_details td WHERE td.item_id = i.id);

-- ---------------------------------------------------------------------------
-- 2) Backfill legacy_code before changing items.code (if not already done)
-- ---------------------------------------------------------------------------
-- UPDATE items SET legacy_code = code WHERE legacy_code IS NULL AND code <> '';

-- ---------------------------------------------------------------------------
-- 3) Example: convert slash pcodes to hyphen (manufactured items)
-- ---------------------------------------------------------------------------
-- UPDATE items SET pcode = REPLACE(pcode, '/', '-') WHERE pcode LIKE '%/%';
-- UPDATE item_groups SET name = REPLACE(name, '/', '-') WHERE name LIKE '%/%';

-- ---------------------------------------------------------------------------
-- 4) After Laravel migration runs, verify Jubelio can still resolve SKUs:
-- ---------------------------------------------------------------------------
-- SELECT id, code, legacy_code, name FROM items
-- WHERE UPPER(legacy_code) IN ('OLD-SKU-1', 'OLD-SKU-2');
