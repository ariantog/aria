-- One-off: align transaction party types for Novian Kurnia Bandung (customers.id = 2721, type 7 reseller).
-- Root cause: L10 / early migration stored sender_type/receiver_type = 1 (customer) on money rows
-- while addrbook is type 7. L12 incremental balance replay skips those rows (entityTransactionsQuery).
--
-- Run on production MySQL during a quiet window. Review counts before committing.
-- Does NOT touch warehouse stock or transaction totals.

-- 0) Confirm addrbook (expect type = 7, name Novian Kurnia Bandung)
SELECT id, name, type FROM customers WHERE id = 2721;

-- 1) Pre-check: rows that will be updated
SELECT COUNT(*) AS sender_rows_to_fix
FROM transactions
WHERE sender_id = 2721 AND sender_type = 1;

SELECT COUNT(*) AS receiver_rows_to_fix
FROM transactions
WHERE receiver_id = 2721 AND receiver_type = 1;

-- Optional: list any other type skew (should be 0 or investigate before updating)
SELECT id, date, type, invoice, sender_id, sender_type, receiver_id, receiver_type
FROM transactions
WHERE (
    (sender_id = 2721 AND sender_type NOT IN (1, 7))
    OR (receiver_id = 2721 AND receiver_type NOT IN (1, 7))
)
ORDER BY date DESC, id DESC
LIMIT 50;

START TRANSACTION;

UPDATE transactions
SET sender_type = 7
WHERE sender_id = 2721
  AND sender_type = 1;

UPDATE transactions
SET receiver_type = 7
WHERE receiver_id = 2721
  AND receiver_type = 1;

-- 2) Post-check: should return 0 mismatches vs customers.type
SELECT COUNT(*) AS mismatched_after
FROM transactions t
INNER JOIN customers c ON c.id = 2721
WHERE (t.sender_id = 2721 AND t.sender_type <> c.type)
   OR (t.receiver_id = 2721 AND t.receiver_type <> c.type);

COMMIT;

-- 3) Rebuild money running balances for this addrbook only (CLI on app server):
--    php artisan app:recalculate-running-balances --addrbook=2721
--
-- Or System Settings → Running Balances, limit to addrbook 2721 if the UI supports it.
--
-- Spot-check invoice 634244 after rebuild: paired sell + cash in should net customer balance on latest row.