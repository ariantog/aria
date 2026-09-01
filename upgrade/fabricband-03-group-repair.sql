-- FABRICBAND-03 surgical repair (production MySQL)
--
-- These 44 item_group rows already share master = FABRICBAND-03, so the
-- Group List parent is already a single FABRICBAND-03. Do NOT merge them:
-- each row is a color variant (LIGHT/MEDIUM/HEAVY are sizes on items).
--
-- This script only:
--   1. Renames each color group to "FABRIC BAND - {COLOR}" (strips LIGHT/MEDIUM/HEAVY)
--   2. Renames each SKU to "FABRIC BAND - {COLOR} - {SIZE}"
--   3. Fixes items.id = 89310 pcode (was FABRICBAND-03-07-MEDIUM)
--
-- item_group.name is UNIQUE. Run section A first. If it returns any row, STOP.
-- All 44 groups are referenced by the 73 SKUs below — no DELETE.
--
-- Note: items.id = 89310 is currently named TOSKA but its SKU/group variant is 07.
--       This script keeps 07 (Jubelio item 15017). Rename 07 → TOSKA separately
--       if that color mapping is intentional.

-- =============================================================================
-- A. Preflight (must return 0 rows)
-- =============================================================================

SELECT other.id AS conflicting_group_id,
       other.name AS conflicting_name,
       target.id AS fabricband_group_id
FROM (
    SELECT 26655 AS id, 'LIMEYELLOW' AS variant, 'FABRIC BAND - LIMEYELLOW' AS desired_name
    UNION ALL
    SELECT 26656 AS id, 'MINT' AS variant, 'FABRIC BAND - MINT' AS desired_name
    UNION ALL
    SELECT 26658 AS id, 'IVORY' AS variant, 'FABRIC BAND - IVORY' AS desired_name
    UNION ALL
    SELECT 26659 AS id, 'LATTE' AS variant, 'FABRIC BAND - LATTE' AS desired_name
    UNION ALL
    SELECT 26660 AS id, 'BLUE' AS variant, 'FABRIC BAND - BLUE' AS desired_name
    UNION ALL
    SELECT 26661 AS id, 'BABYBLUE' AS variant, 'FABRIC BAND - BABYBLUE' AS desired_name
    UNION ALL
    SELECT 26662 AS id, 'NEONORANGE' AS variant, 'FABRIC BAND - NEONORANGE' AS desired_name
    UNION ALL
    SELECT 26663 AS id, 'BABYPINK' AS variant, 'FABRIC BAND - BABYPINK' AS desired_name
    UNION ALL
    SELECT 26664 AS id, 'GREEN' AS variant, 'FABRIC BAND - GREEN' AS desired_name
    UNION ALL
    SELECT 26665 AS id, 'TERRACOTTA' AS variant, 'FABRIC BAND - TERRACOTTA' AS desired_name
    UNION ALL
    SELECT 26666 AS id, 'BUTTERSCOTCH' AS variant, 'FABRIC BAND - BUTTERSCOTCH' AS desired_name
    UNION ALL
    SELECT 26667 AS id, 'PINK' AS variant, 'FABRIC BAND - PINK' AS desired_name
    UNION ALL
    SELECT 26668 AS id, 'GREY' AS variant, 'FABRIC BAND - GREY' AS desired_name
    UNION ALL
    SELECT 26669 AS id, 'FRUITPUNCH' AS variant, 'FABRIC BAND - FRUITPUNCH' AS desired_name
    UNION ALL
    SELECT 26670 AS id, 'LILAC' AS variant, 'FABRIC BAND - LILAC' AS desired_name
    UNION ALL
    SELECT 26671 AS id, 'MAUVE' AS variant, 'FABRIC BAND - MAUVE' AS desired_name
    UNION ALL
    SELECT 26672 AS id, 'MATCHA' AS variant, 'FABRIC BAND - MATCHA' AS desired_name
    UNION ALL
    SELECT 26673 AS id, 'RED' AS variant, 'FABRIC BAND - RED' AS desired_name
    UNION ALL
    SELECT 26674 AS id, 'BLACK' AS variant, 'FABRIC BAND - BLACK' AS desired_name
    UNION ALL
    SELECT 26675 AS id, 'FUSCHIA' AS variant, 'FABRIC BAND - FUSCHIA' AS desired_name
    UNION ALL
    SELECT 26676 AS id, 'STRAWBERRY' AS variant, 'FABRIC BAND - STRAWBERRY' AS desired_name
    UNION ALL
    SELECT 26677 AS id, 'DARKGREY' AS variant, 'FABRIC BAND - DARKGREY' AS desired_name
    UNION ALL
    SELECT 26678 AS id, 'LIGHTGREY' AS variant, 'FABRIC BAND - LIGHTGREY' AS desired_name
    UNION ALL
    SELECT 26679 AS id, 'DARKGREY2' AS variant, 'FABRIC BAND - DARKGREY2' AS desired_name
    UNION ALL
    SELECT 26680 AS id, 'PEACH' AS variant, 'FABRIC BAND - PEACH' AS desired_name
    UNION ALL
    SELECT 26681 AS id, 'PURPLE' AS variant, 'FABRIC BAND - PURPLE' AS desired_name
    UNION ALL
    SELECT 26682 AS id, 'TEAL' AS variant, 'FABRIC BAND - TEAL' AS desired_name
    UNION ALL
    SELECT 26683 AS id, '15' AS variant, 'FABRIC BAND - 15' AS desired_name
    UNION ALL
    SELECT 26684 AS id, '13' AS variant, 'FABRIC BAND - 13' AS desired_name
    UNION ALL
    SELECT 26685 AS id, '12' AS variant, 'FABRIC BAND - 12' AS desired_name
    UNION ALL
    SELECT 26686 AS id, '11' AS variant, 'FABRIC BAND - 11' AS desired_name
    UNION ALL
    SELECT 26687 AS id, 'LIMEGREEN' AS variant, 'FABRIC BAND - LIMEGREEN' AS desired_name
    UNION ALL
    SELECT 26688 AS id, '14' AS variant, 'FABRIC BAND - 14' AS desired_name
    UNION ALL
    SELECT 26689 AS id, '10' AS variant, 'FABRIC BAND - 10' AS desired_name
    UNION ALL
    SELECT 26690 AS id, '09' AS variant, 'FABRIC BAND - 09' AS desired_name
    UNION ALL
    SELECT 26691 AS id, '08' AS variant, 'FABRIC BAND - 08' AS desired_name
    UNION ALL
    SELECT 26692 AS id, '07' AS variant, 'FABRIC BAND - 07' AS desired_name
    UNION ALL
    SELECT 26693 AS id, '06' AS variant, 'FABRIC BAND - 06' AS desired_name
    UNION ALL
    SELECT 26694 AS id, '05' AS variant, 'FABRIC BAND - 05' AS desired_name
    UNION ALL
    SELECT 26695 AS id, '04' AS variant, 'FABRIC BAND - 04' AS desired_name
    UNION ALL
    SELECT 26696 AS id, '03' AS variant, 'FABRIC BAND - 03' AS desired_name
    UNION ALL
    SELECT 26697 AS id, '02' AS variant, 'FABRIC BAND - 02' AS desired_name
    UNION ALL
    SELECT 26698 AS id, '01' AS variant, 'FABRIC BAND - 01' AS desired_name
    UNION ALL
    SELECT 26699 AS id, 'BROWN' AS variant, 'FABRIC BAND - BROWN' AS desired_name
) AS target
INNER JOIN item_group AS other
        ON other.name = target.desired_name
       AND other.id <> target.id;

SELECT g.id, g.master, g.variant, g.name
FROM item_group AS g
WHERE g.id IN (26655, 26656, 26658, 26659, 26660, 26661, 26662, 26663, 26664, 26665, 26666, 26667, 26668, 26669, 26670, 26671, 26672, 26673, 26674, 26675, 26676, 26677, 26678, 26679, 26680, 26681, 26682, 26683, 26684, 26685, 26686, 26687, 26688, 26689, 26690, 26691, 26692, 26693, 26694, 26695, 26696, 26697, 26698, 26699)
  AND (g.master <> 'FABRICBAND-03' OR g.variant NOT IN (
        SELECT variant FROM (
    SELECT 26655 AS id, 'LIMEYELLOW' AS variant, 'FABRIC BAND - LIMEYELLOW' AS desired_name
    UNION ALL
    SELECT 26656 AS id, 'MINT' AS variant, 'FABRIC BAND - MINT' AS desired_name
    UNION ALL
    SELECT 26658 AS id, 'IVORY' AS variant, 'FABRIC BAND - IVORY' AS desired_name
    UNION ALL
    SELECT 26659 AS id, 'LATTE' AS variant, 'FABRIC BAND - LATTE' AS desired_name
    UNION ALL
    SELECT 26660 AS id, 'BLUE' AS variant, 'FABRIC BAND - BLUE' AS desired_name
    UNION ALL
    SELECT 26661 AS id, 'BABYBLUE' AS variant, 'FABRIC BAND - BABYBLUE' AS desired_name
    UNION ALL
    SELECT 26662 AS id, 'NEONORANGE' AS variant, 'FABRIC BAND - NEONORANGE' AS desired_name
    UNION ALL
    SELECT 26663 AS id, 'BABYPINK' AS variant, 'FABRIC BAND - BABYPINK' AS desired_name
    UNION ALL
    SELECT 26664 AS id, 'GREEN' AS variant, 'FABRIC BAND - GREEN' AS desired_name
    UNION ALL
    SELECT 26665 AS id, 'TERRACOTTA' AS variant, 'FABRIC BAND - TERRACOTTA' AS desired_name
    UNION ALL
    SELECT 26666 AS id, 'BUTTERSCOTCH' AS variant, 'FABRIC BAND - BUTTERSCOTCH' AS desired_name
    UNION ALL
    SELECT 26667 AS id, 'PINK' AS variant, 'FABRIC BAND - PINK' AS desired_name
    UNION ALL
    SELECT 26668 AS id, 'GREY' AS variant, 'FABRIC BAND - GREY' AS desired_name
    UNION ALL
    SELECT 26669 AS id, 'FRUITPUNCH' AS variant, 'FABRIC BAND - FRUITPUNCH' AS desired_name
    UNION ALL
    SELECT 26670 AS id, 'LILAC' AS variant, 'FABRIC BAND - LILAC' AS desired_name
    UNION ALL
    SELECT 26671 AS id, 'MAUVE' AS variant, 'FABRIC BAND - MAUVE' AS desired_name
    UNION ALL
    SELECT 26672 AS id, 'MATCHA' AS variant, 'FABRIC BAND - MATCHA' AS desired_name
    UNION ALL
    SELECT 26673 AS id, 'RED' AS variant, 'FABRIC BAND - RED' AS desired_name
    UNION ALL
    SELECT 26674 AS id, 'BLACK' AS variant, 'FABRIC BAND - BLACK' AS desired_name
    UNION ALL
    SELECT 26675 AS id, 'FUSCHIA' AS variant, 'FABRIC BAND - FUSCHIA' AS desired_name
    UNION ALL
    SELECT 26676 AS id, 'STRAWBERRY' AS variant, 'FABRIC BAND - STRAWBERRY' AS desired_name
    UNION ALL
    SELECT 26677 AS id, 'DARKGREY' AS variant, 'FABRIC BAND - DARKGREY' AS desired_name
    UNION ALL
    SELECT 26678 AS id, 'LIGHTGREY' AS variant, 'FABRIC BAND - LIGHTGREY' AS desired_name
    UNION ALL
    SELECT 26679 AS id, 'DARKGREY2' AS variant, 'FABRIC BAND - DARKGREY2' AS desired_name
    UNION ALL
    SELECT 26680 AS id, 'PEACH' AS variant, 'FABRIC BAND - PEACH' AS desired_name
    UNION ALL
    SELECT 26681 AS id, 'PURPLE' AS variant, 'FABRIC BAND - PURPLE' AS desired_name
    UNION ALL
    SELECT 26682 AS id, 'TEAL' AS variant, 'FABRIC BAND - TEAL' AS desired_name
    UNION ALL
    SELECT 26683 AS id, '15' AS variant, 'FABRIC BAND - 15' AS desired_name
    UNION ALL
    SELECT 26684 AS id, '13' AS variant, 'FABRIC BAND - 13' AS desired_name
    UNION ALL
    SELECT 26685 AS id, '12' AS variant, 'FABRIC BAND - 12' AS desired_name
    UNION ALL
    SELECT 26686 AS id, '11' AS variant, 'FABRIC BAND - 11' AS desired_name
    UNION ALL
    SELECT 26687 AS id, 'LIMEGREEN' AS variant, 'FABRIC BAND - LIMEGREEN' AS desired_name
    UNION ALL
    SELECT 26688 AS id, '14' AS variant, 'FABRIC BAND - 14' AS desired_name
    UNION ALL
    SELECT 26689 AS id, '10' AS variant, 'FABRIC BAND - 10' AS desired_name
    UNION ALL
    SELECT 26690 AS id, '09' AS variant, 'FABRIC BAND - 09' AS desired_name
    UNION ALL
    SELECT 26691 AS id, '08' AS variant, 'FABRIC BAND - 08' AS desired_name
    UNION ALL
    SELECT 26692 AS id, '07' AS variant, 'FABRIC BAND - 07' AS desired_name
    UNION ALL
    SELECT 26693 AS id, '06' AS variant, 'FABRIC BAND - 06' AS desired_name
    UNION ALL
    SELECT 26694 AS id, '05' AS variant, 'FABRIC BAND - 05' AS desired_name
    UNION ALL
    SELECT 26695 AS id, '04' AS variant, 'FABRIC BAND - 04' AS desired_name
    UNION ALL
    SELECT 26696 AS id, '03' AS variant, 'FABRIC BAND - 03' AS desired_name
    UNION ALL
    SELECT 26697 AS id, '02' AS variant, 'FABRIC BAND - 02' AS desired_name
    UNION ALL
    SELECT 26698 AS id, '01' AS variant, 'FABRIC BAND - 01' AS desired_name
    UNION ALL
    SELECT 26699 AS id, 'BROWN' AS variant, 'FABRIC BAND - BROWN' AS desired_name
        ) AS expected WHERE expected.id = g.id
      ));

SELECT i.id, i.group_id, i.code, i.pcode
FROM items AS i
WHERE i.id IN (87097, 87098, 87099, 87107, 87108, 88672, 88673, 88674, 88675, 88676, 88677, 88678, 88679, 88680, 88681, 88682, 88683, 88704, 89310, 89311, 89312, 89313, 89314, 89315, 89316, 89317, 89318, 89319, 89320, 89321, 89386, 89396, 90553, 90554, 90555, 90574, 90575, 91587, 91685, 91686, 91687, 91688, 91689, 91690, 91691, 91692, 91693, 91744, 91745, 91746, 91748, 91749, 91750, 91751, 93935, 93968, 94438, 94439, 94440, 94441, 94442, 98162, 98163, 98312, 98313, 98418, 99117, 101951, 101952, 101953, 101954, 101956, 102027)
  AND (
        i.deleted_at IS NOT NULL
     OR i.group_id NOT IN (26655, 26656, 26658, 26659, 26660, 26661, 26662, 26663, 26664, 26665, 26666, 26667, 26668, 26669, 26670, 26671, 26672, 26673, 26674, 26675, 26676, 26677, 26678, 26679, 26680, 26681, 26682, 26683, 26684, 26685, 26686, 26687, 26688, 26689, 26690, 26691, 26692, 26693, 26694, 26695, 26696, 26697, 26698, 26699)
     OR i.code NOT IN (
        SELECT code FROM (
    SELECT 87097 AS id, 26699 AS group_id, 'FABRICBAND-03-BROWN-LIGHT' AS code, 'FABRIC BAND - BROWN - LIGHT' AS desired_name
    UNION ALL
    SELECT 87098 AS id, 26674 AS group_id, 'FABRICBAND-03-BLACK-MEDIUM' AS code, 'FABRIC BAND - BLACK - MEDIUM' AS desired_name
    UNION ALL
    SELECT 87099 AS id, 26681 AS group_id, 'FABRICBAND-03-PURPLE-MEDIUM' AS code, 'FABRIC BAND - PURPLE - MEDIUM' AS desired_name
    UNION ALL
    SELECT 87107 AS id, 26659 AS group_id, 'FABRICBAND-03-LATTE-LIGHT' AS code, 'FABRIC BAND - LATTE - LIGHT' AS desired_name
    UNION ALL
    SELECT 87108 AS id, 26667 AS group_id, 'FABRICBAND-03-PINK-MEDIUM' AS code, 'FABRIC BAND - PINK - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88672 AS id, 26698 AS group_id, 'FABRICBAND-03-01-MEDIUM' AS code, 'FABRIC BAND - 01 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88673 AS id, 26698 AS group_id, 'FABRICBAND-03-01-LIGHT' AS code, 'FABRIC BAND - 01 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88674 AS id, 26697 AS group_id, 'FABRICBAND-03-02-MEDIUM' AS code, 'FABRIC BAND - 02 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88675 AS id, 26696 AS group_id, 'FABRICBAND-03-03-LIGHT' AS code, 'FABRIC BAND - 03 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88676 AS id, 26695 AS group_id, 'FABRICBAND-03-04-LIGHT' AS code, 'FABRIC BAND - 04 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88677 AS id, 26694 AS group_id, 'FABRICBAND-03-05-LIGHT' AS code, 'FABRIC BAND - 05 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88678 AS id, 26693 AS group_id, 'FABRICBAND-03-06-LIGHT' AS code, 'FABRIC BAND - 06 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88679 AS id, 26663 AS group_id, 'FABRICBAND-03-BABYPINK-LIGHT' AS code, 'FABRIC BAND - BABYPINK - LIGHT' AS desired_name
    UNION ALL
    SELECT 88680 AS id, 26697 AS group_id, 'FABRICBAND-03-02-LIGHT' AS code, 'FABRIC BAND - 02 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88681 AS id, 26696 AS group_id, 'FABRICBAND-03-03-MEDIUM' AS code, 'FABRIC BAND - 03 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88682 AS id, 26695 AS group_id, 'FABRICBAND-03-04-MEDIUM' AS code, 'FABRIC BAND - 04 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88683 AS id, 26694 AS group_id, 'FABRICBAND-03-05-MEDIUM' AS code, 'FABRIC BAND - 05 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88704 AS id, 26693 AS group_id, 'FABRICBAND-03-06-MEDIUM' AS code, 'FABRIC BAND - 06 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89310 AS id, 26692 AS group_id, 'FABRICBAND-03-07-MEDIUM' AS code, 'FABRIC BAND - 07 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89311 AS id, 26691 AS group_id, 'FABRICBAND-03-08-MEDIUM' AS code, 'FABRIC BAND - 08 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89312 AS id, 26690 AS group_id, 'FABRICBAND-03-09-MEDIUM' AS code, 'FABRIC BAND - 09 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89313 AS id, 26689 AS group_id, 'FABRICBAND-03-10-MEDIUM' AS code, 'FABRIC BAND - 10 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89314 AS id, 26691 AS group_id, 'FABRICBAND-03-08-LIGHT' AS code, 'FABRIC BAND - 08 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89315 AS id, 26690 AS group_id, 'FABRICBAND-03-09-LIGHT' AS code, 'FABRIC BAND - 09 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89316 AS id, 26689 AS group_id, 'FABRICBAND-03-10-LIGHT' AS code, 'FABRIC BAND - 10 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89317 AS id, 26686 AS group_id, 'FABRICBAND-03-11-LIGHT' AS code, 'FABRIC BAND - 11 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89318 AS id, 26685 AS group_id, 'FABRICBAND-03-12-LIGHT' AS code, 'FABRIC BAND - 12 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89319 AS id, 26684 AS group_id, 'FABRICBAND-03-13-LIGHT' AS code, 'FABRIC BAND - 13 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89320 AS id, 26688 AS group_id, 'FABRICBAND-03-14-LIGHT' AS code, 'FABRIC BAND - 14 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89321 AS id, 26687 AS group_id, 'FABRICBAND-03-LIMEGREEN-MEDIUM' AS code, 'FABRIC BAND - LIMEGREEN - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89386 AS id, 26664 AS group_id, 'FABRICBAND-03-GREEN-LIGHT' AS code, 'FABRIC BAND - GREEN - LIGHT' AS desired_name
    UNION ALL
    SELECT 89396 AS id, 26660 AS group_id, 'FABRICBAND-03-BLUE-LIGHT' AS code, 'FABRIC BAND - BLUE - LIGHT' AS desired_name
    UNION ALL
    SELECT 90553 AS id, 26686 AS group_id, 'FABRICBAND-03-11-MEDIUM' AS code, 'FABRIC BAND - 11 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 90554 AS id, 26685 AS group_id, 'FABRICBAND-03-12-MEDIUM' AS code, 'FABRIC BAND - 12 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 90555 AS id, 26684 AS group_id, 'FABRICBAND-03-13-MEDIUM' AS code, 'FABRIC BAND - 13 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 90574 AS id, 26655 AS group_id, 'FABRICBAND-03-LIMEYELLOW-MEDIUM' AS code, 'FABRIC BAND - LIMEYELLOW - MEDIUM' AS desired_name
    UNION ALL
    SELECT 90575 AS id, 26683 AS group_id, 'FABRICBAND-03-15-MEDIUM' AS code, 'FABRIC BAND - 15 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 91587 AS id, 26667 AS group_id, 'FABRICBAND-03-PINK-LIGHT' AS code, 'FABRIC BAND - PINK - LIGHT' AS desired_name
    UNION ALL
    SELECT 91685 AS id, 26682 AS group_id, 'FABRICBAND-03-TEAL-HEAVY' AS code, 'FABRIC BAND - TEAL - HEAVY' AS desired_name
    UNION ALL
    SELECT 91686 AS id, 26656 AS group_id, 'FABRICBAND-03-MINT-HEAVY' AS code, 'FABRIC BAND - MINT - HEAVY' AS desired_name
    UNION ALL
    SELECT 91687 AS id, 26681 AS group_id, 'FABRICBAND-03-PURPLE-HEAVY' AS code, 'FABRIC BAND - PURPLE - HEAVY' AS desired_name
    UNION ALL
    SELECT 91688 AS id, 26680 AS group_id, 'FABRICBAND-03-PEACH-HEAVY' AS code, 'FABRIC BAND - PEACH - HEAVY' AS desired_name
    UNION ALL
    SELECT 91689 AS id, 26679 AS group_id, 'FABRICBAND-03-DARKGREY2-HEAVY' AS code, 'FABRIC BAND - DARKGREY2 - HEAVY' AS desired_name
    UNION ALL
    SELECT 91690 AS id, 26678 AS group_id, 'FABRICBAND-03-LIGHTGREY-HEAVY' AS code, 'FABRIC BAND - LIGHTGREY - HEAVY' AS desired_name
    UNION ALL
    SELECT 91691 AS id, 26677 AS group_id, 'FABRICBAND-03-DARKGREY-HEAVY' AS code, 'FABRIC BAND - DARKGREY - HEAVY' AS desired_name
    UNION ALL
    SELECT 91692 AS id, 26676 AS group_id, 'FABRICBAND-03-STRAWBERRY-HEAVY' AS code, 'FABRIC BAND - STRAWBERRY - HEAVY' AS desired_name
    UNION ALL
    SELECT 91693 AS id, 26675 AS group_id, 'FABRICBAND-03-FUSCHIA-MEDIUM' AS code, 'FABRIC BAND - FUSCHIA - MEDIUM' AS desired_name
    UNION ALL
    SELECT 91744 AS id, 26674 AS group_id, 'FABRICBAND-03-BLACK-HEAVY' AS code, 'FABRIC BAND - BLACK - HEAVY' AS desired_name
    UNION ALL
    SELECT 91745 AS id, 26662 AS group_id, 'FABRICBAND-03-NEONORANGE-MEDIUM' AS code, 'FABRIC BAND - NEONORANGE - MEDIUM' AS desired_name
    UNION ALL
    SELECT 91746 AS id, 26673 AS group_id, 'FABRICBAND-03-RED-MEDIUM' AS code, 'FABRIC BAND - RED - MEDIUM' AS desired_name
    UNION ALL
    SELECT 91748 AS id, 26671 AS group_id, 'FABRICBAND-03-MAUVE-HEAVY' AS code, 'FABRIC BAND - MAUVE - HEAVY' AS desired_name
    UNION ALL
    SELECT 91749 AS id, 26655 AS group_id, 'FABRICBAND-03-LIMEYELLOW-HEAVY' AS code, 'FABRIC BAND - LIMEYELLOW - HEAVY' AS desired_name
    UNION ALL
    SELECT 91750 AS id, 26673 AS group_id, 'FABRICBAND-03-RED-HEAVY' AS code, 'FABRIC BAND - RED - HEAVY' AS desired_name
    UNION ALL
    SELECT 91751 AS id, 26656 AS group_id, 'FABRICBAND-03-MINT-MEDIUM' AS code, 'FABRIC BAND - MINT - MEDIUM' AS desired_name
    UNION ALL
    SELECT 93935 AS id, 26673 AS group_id, 'FABRICBAND-03-RED-LIGHT' AS code, 'FABRIC BAND - RED - LIGHT' AS desired_name
    UNION ALL
    SELECT 93968 AS id, 26672 AS group_id, 'FABRICBAND-03-MATCHA-MEDIUM' AS code, 'FABRIC BAND - MATCHA - MEDIUM' AS desired_name
    UNION ALL
    SELECT 94438 AS id, 26666 AS group_id, 'FABRICBAND-03-BUTTERSCOTCH-MEDIUM' AS code, 'FABRIC BAND - BUTTERSCOTCH - MEDIUM' AS desired_name
    UNION ALL
    SELECT 94439 AS id, 26671 AS group_id, 'FABRICBAND-03-MAUVE-MEDIUM' AS code, 'FABRIC BAND - MAUVE - MEDIUM' AS desired_name
    UNION ALL
    SELECT 94440 AS id, 26670 AS group_id, 'FABRICBAND-03-LILAC-LIGHT' AS code, 'FABRIC BAND - LILAC - LIGHT' AS desired_name
    UNION ALL
    SELECT 94441 AS id, 26658 AS group_id, 'FABRICBAND-03-IVORY-LIGHT' AS code, 'FABRIC BAND - IVORY - LIGHT' AS desired_name
    UNION ALL
    SELECT 94442 AS id, 26669 AS group_id, 'FABRICBAND-03-FRUITPUNCH-LIGHT' AS code, 'FABRIC BAND - FRUITPUNCH - LIGHT' AS desired_name
    UNION ALL
    SELECT 98162 AS id, 26664 AS group_id, 'FABRICBAND-03-GREEN-MEDIUM' AS code, 'FABRIC BAND - GREEN - MEDIUM' AS desired_name
    UNION ALL
    SELECT 98163 AS id, 26669 AS group_id, 'FABRICBAND-03-FRUITPUNCH-MEDIUM' AS code, 'FABRIC BAND - FRUITPUNCH - MEDIUM' AS desired_name
    UNION ALL
    SELECT 98312 AS id, 26668 AS group_id, 'FABRICBAND-03-GREY-HEAVY' AS code, 'FABRIC BAND - GREY - HEAVY' AS desired_name
    UNION ALL
    SELECT 98313 AS id, 26667 AS group_id, 'FABRICBAND-03-PINK-HEAVY' AS code, 'FABRIC BAND - PINK - HEAVY' AS desired_name
    UNION ALL
    SELECT 98418 AS id, 26666 AS group_id, 'FABRICBAND-03-BUTTERSCOTCH-LIGHT' AS code, 'FABRIC BAND - BUTTERSCOTCH - LIGHT' AS desired_name
    UNION ALL
    SELECT 99117 AS id, 26665 AS group_id, 'FABRICBAND-03-TERRACOTTA-MEDIUM' AS code, 'FABRIC BAND - TERRACOTTA - MEDIUM' AS desired_name
    UNION ALL
    SELECT 101951 AS id, 26664 AS group_id, 'FABRICBAND-03-GREEN-HEAVY' AS code, 'FABRIC BAND - GREEN - HEAVY' AS desired_name
    UNION ALL
    SELECT 101952 AS id, 26660 AS group_id, 'FABRICBAND-03-BLUE-HEAVY' AS code, 'FABRIC BAND - BLUE - HEAVY' AS desired_name
    UNION ALL
    SELECT 101953 AS id, 26663 AS group_id, 'FABRICBAND-03-BABYPINK-MEDIUM' AS code, 'FABRIC BAND - BABYPINK - MEDIUM' AS desired_name
    UNION ALL
    SELECT 101954 AS id, 26662 AS group_id, 'FABRICBAND-03-NEONORANGE-LIGHT' AS code, 'FABRIC BAND - NEONORANGE - LIGHT' AS desired_name
    UNION ALL
    SELECT 101956 AS id, 26661 AS group_id, 'FABRICBAND-03-BABYBLUE-LIGHT' AS code, 'FABRIC BAND - BABYBLUE - LIGHT' AS desired_name
    UNION ALL
    SELECT 102027 AS id, 26660 AS group_id, 'FABRICBAND-03-BLUE-MEDIUM' AS code, 'FABRIC BAND - BLUE - MEDIUM' AS desired_name
        ) AS expected WHERE expected.id = i.id
      )
  );

-- =============================================================================
-- B. Apply (run only after A is empty)
-- =============================================================================

START TRANSACTION;

UPDATE item_group AS g
INNER JOIN (
    SELECT 26655 AS id, 'LIMEYELLOW' AS variant, 'FABRIC BAND - LIMEYELLOW' AS desired_name
    UNION ALL
    SELECT 26656 AS id, 'MINT' AS variant, 'FABRIC BAND - MINT' AS desired_name
    UNION ALL
    SELECT 26658 AS id, 'IVORY' AS variant, 'FABRIC BAND - IVORY' AS desired_name
    UNION ALL
    SELECT 26659 AS id, 'LATTE' AS variant, 'FABRIC BAND - LATTE' AS desired_name
    UNION ALL
    SELECT 26660 AS id, 'BLUE' AS variant, 'FABRIC BAND - BLUE' AS desired_name
    UNION ALL
    SELECT 26661 AS id, 'BABYBLUE' AS variant, 'FABRIC BAND - BABYBLUE' AS desired_name
    UNION ALL
    SELECT 26662 AS id, 'NEONORANGE' AS variant, 'FABRIC BAND - NEONORANGE' AS desired_name
    UNION ALL
    SELECT 26663 AS id, 'BABYPINK' AS variant, 'FABRIC BAND - BABYPINK' AS desired_name
    UNION ALL
    SELECT 26664 AS id, 'GREEN' AS variant, 'FABRIC BAND - GREEN' AS desired_name
    UNION ALL
    SELECT 26665 AS id, 'TERRACOTTA' AS variant, 'FABRIC BAND - TERRACOTTA' AS desired_name
    UNION ALL
    SELECT 26666 AS id, 'BUTTERSCOTCH' AS variant, 'FABRIC BAND - BUTTERSCOTCH' AS desired_name
    UNION ALL
    SELECT 26667 AS id, 'PINK' AS variant, 'FABRIC BAND - PINK' AS desired_name
    UNION ALL
    SELECT 26668 AS id, 'GREY' AS variant, 'FABRIC BAND - GREY' AS desired_name
    UNION ALL
    SELECT 26669 AS id, 'FRUITPUNCH' AS variant, 'FABRIC BAND - FRUITPUNCH' AS desired_name
    UNION ALL
    SELECT 26670 AS id, 'LILAC' AS variant, 'FABRIC BAND - LILAC' AS desired_name
    UNION ALL
    SELECT 26671 AS id, 'MAUVE' AS variant, 'FABRIC BAND - MAUVE' AS desired_name
    UNION ALL
    SELECT 26672 AS id, 'MATCHA' AS variant, 'FABRIC BAND - MATCHA' AS desired_name
    UNION ALL
    SELECT 26673 AS id, 'RED' AS variant, 'FABRIC BAND - RED' AS desired_name
    UNION ALL
    SELECT 26674 AS id, 'BLACK' AS variant, 'FABRIC BAND - BLACK' AS desired_name
    UNION ALL
    SELECT 26675 AS id, 'FUSCHIA' AS variant, 'FABRIC BAND - FUSCHIA' AS desired_name
    UNION ALL
    SELECT 26676 AS id, 'STRAWBERRY' AS variant, 'FABRIC BAND - STRAWBERRY' AS desired_name
    UNION ALL
    SELECT 26677 AS id, 'DARKGREY' AS variant, 'FABRIC BAND - DARKGREY' AS desired_name
    UNION ALL
    SELECT 26678 AS id, 'LIGHTGREY' AS variant, 'FABRIC BAND - LIGHTGREY' AS desired_name
    UNION ALL
    SELECT 26679 AS id, 'DARKGREY2' AS variant, 'FABRIC BAND - DARKGREY2' AS desired_name
    UNION ALL
    SELECT 26680 AS id, 'PEACH' AS variant, 'FABRIC BAND - PEACH' AS desired_name
    UNION ALL
    SELECT 26681 AS id, 'PURPLE' AS variant, 'FABRIC BAND - PURPLE' AS desired_name
    UNION ALL
    SELECT 26682 AS id, 'TEAL' AS variant, 'FABRIC BAND - TEAL' AS desired_name
    UNION ALL
    SELECT 26683 AS id, '15' AS variant, 'FABRIC BAND - 15' AS desired_name
    UNION ALL
    SELECT 26684 AS id, '13' AS variant, 'FABRIC BAND - 13' AS desired_name
    UNION ALL
    SELECT 26685 AS id, '12' AS variant, 'FABRIC BAND - 12' AS desired_name
    UNION ALL
    SELECT 26686 AS id, '11' AS variant, 'FABRIC BAND - 11' AS desired_name
    UNION ALL
    SELECT 26687 AS id, 'LIMEGREEN' AS variant, 'FABRIC BAND - LIMEGREEN' AS desired_name
    UNION ALL
    SELECT 26688 AS id, '14' AS variant, 'FABRIC BAND - 14' AS desired_name
    UNION ALL
    SELECT 26689 AS id, '10' AS variant, 'FABRIC BAND - 10' AS desired_name
    UNION ALL
    SELECT 26690 AS id, '09' AS variant, 'FABRIC BAND - 09' AS desired_name
    UNION ALL
    SELECT 26691 AS id, '08' AS variant, 'FABRIC BAND - 08' AS desired_name
    UNION ALL
    SELECT 26692 AS id, '07' AS variant, 'FABRIC BAND - 07' AS desired_name
    UNION ALL
    SELECT 26693 AS id, '06' AS variant, 'FABRIC BAND - 06' AS desired_name
    UNION ALL
    SELECT 26694 AS id, '05' AS variant, 'FABRIC BAND - 05' AS desired_name
    UNION ALL
    SELECT 26695 AS id, '04' AS variant, 'FABRIC BAND - 04' AS desired_name
    UNION ALL
    SELECT 26696 AS id, '03' AS variant, 'FABRIC BAND - 03' AS desired_name
    UNION ALL
    SELECT 26697 AS id, '02' AS variant, 'FABRIC BAND - 02' AS desired_name
    UNION ALL
    SELECT 26698 AS id, '01' AS variant, 'FABRIC BAND - 01' AS desired_name
    UNION ALL
    SELECT 26699 AS id, 'BROWN' AS variant, 'FABRIC BAND - BROWN' AS desired_name
) AS target ON target.id = g.id
SET g.name = target.desired_name
WHERE g.master = 'FABRICBAND-03'
  AND g.variant = target.variant
  AND g.name <> target.desired_name;

UPDATE items AS i
INNER JOIN (
    SELECT 87097 AS id, 26699 AS group_id, 'FABRICBAND-03-BROWN-LIGHT' AS code, 'FABRIC BAND - BROWN - LIGHT' AS desired_name
    UNION ALL
    SELECT 87098 AS id, 26674 AS group_id, 'FABRICBAND-03-BLACK-MEDIUM' AS code, 'FABRIC BAND - BLACK - MEDIUM' AS desired_name
    UNION ALL
    SELECT 87099 AS id, 26681 AS group_id, 'FABRICBAND-03-PURPLE-MEDIUM' AS code, 'FABRIC BAND - PURPLE - MEDIUM' AS desired_name
    UNION ALL
    SELECT 87107 AS id, 26659 AS group_id, 'FABRICBAND-03-LATTE-LIGHT' AS code, 'FABRIC BAND - LATTE - LIGHT' AS desired_name
    UNION ALL
    SELECT 87108 AS id, 26667 AS group_id, 'FABRICBAND-03-PINK-MEDIUM' AS code, 'FABRIC BAND - PINK - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88672 AS id, 26698 AS group_id, 'FABRICBAND-03-01-MEDIUM' AS code, 'FABRIC BAND - 01 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88673 AS id, 26698 AS group_id, 'FABRICBAND-03-01-LIGHT' AS code, 'FABRIC BAND - 01 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88674 AS id, 26697 AS group_id, 'FABRICBAND-03-02-MEDIUM' AS code, 'FABRIC BAND - 02 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88675 AS id, 26696 AS group_id, 'FABRICBAND-03-03-LIGHT' AS code, 'FABRIC BAND - 03 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88676 AS id, 26695 AS group_id, 'FABRICBAND-03-04-LIGHT' AS code, 'FABRIC BAND - 04 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88677 AS id, 26694 AS group_id, 'FABRICBAND-03-05-LIGHT' AS code, 'FABRIC BAND - 05 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88678 AS id, 26693 AS group_id, 'FABRICBAND-03-06-LIGHT' AS code, 'FABRIC BAND - 06 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88679 AS id, 26663 AS group_id, 'FABRICBAND-03-BABYPINK-LIGHT' AS code, 'FABRIC BAND - BABYPINK - LIGHT' AS desired_name
    UNION ALL
    SELECT 88680 AS id, 26697 AS group_id, 'FABRICBAND-03-02-LIGHT' AS code, 'FABRIC BAND - 02 - LIGHT' AS desired_name
    UNION ALL
    SELECT 88681 AS id, 26696 AS group_id, 'FABRICBAND-03-03-MEDIUM' AS code, 'FABRIC BAND - 03 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88682 AS id, 26695 AS group_id, 'FABRICBAND-03-04-MEDIUM' AS code, 'FABRIC BAND - 04 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88683 AS id, 26694 AS group_id, 'FABRICBAND-03-05-MEDIUM' AS code, 'FABRIC BAND - 05 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 88704 AS id, 26693 AS group_id, 'FABRICBAND-03-06-MEDIUM' AS code, 'FABRIC BAND - 06 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89310 AS id, 26692 AS group_id, 'FABRICBAND-03-07-MEDIUM' AS code, 'FABRIC BAND - 07 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89311 AS id, 26691 AS group_id, 'FABRICBAND-03-08-MEDIUM' AS code, 'FABRIC BAND - 08 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89312 AS id, 26690 AS group_id, 'FABRICBAND-03-09-MEDIUM' AS code, 'FABRIC BAND - 09 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89313 AS id, 26689 AS group_id, 'FABRICBAND-03-10-MEDIUM' AS code, 'FABRIC BAND - 10 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89314 AS id, 26691 AS group_id, 'FABRICBAND-03-08-LIGHT' AS code, 'FABRIC BAND - 08 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89315 AS id, 26690 AS group_id, 'FABRICBAND-03-09-LIGHT' AS code, 'FABRIC BAND - 09 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89316 AS id, 26689 AS group_id, 'FABRICBAND-03-10-LIGHT' AS code, 'FABRIC BAND - 10 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89317 AS id, 26686 AS group_id, 'FABRICBAND-03-11-LIGHT' AS code, 'FABRIC BAND - 11 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89318 AS id, 26685 AS group_id, 'FABRICBAND-03-12-LIGHT' AS code, 'FABRIC BAND - 12 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89319 AS id, 26684 AS group_id, 'FABRICBAND-03-13-LIGHT' AS code, 'FABRIC BAND - 13 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89320 AS id, 26688 AS group_id, 'FABRICBAND-03-14-LIGHT' AS code, 'FABRIC BAND - 14 - LIGHT' AS desired_name
    UNION ALL
    SELECT 89321 AS id, 26687 AS group_id, 'FABRICBAND-03-LIMEGREEN-MEDIUM' AS code, 'FABRIC BAND - LIMEGREEN - MEDIUM' AS desired_name
    UNION ALL
    SELECT 89386 AS id, 26664 AS group_id, 'FABRICBAND-03-GREEN-LIGHT' AS code, 'FABRIC BAND - GREEN - LIGHT' AS desired_name
    UNION ALL
    SELECT 89396 AS id, 26660 AS group_id, 'FABRICBAND-03-BLUE-LIGHT' AS code, 'FABRIC BAND - BLUE - LIGHT' AS desired_name
    UNION ALL
    SELECT 90553 AS id, 26686 AS group_id, 'FABRICBAND-03-11-MEDIUM' AS code, 'FABRIC BAND - 11 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 90554 AS id, 26685 AS group_id, 'FABRICBAND-03-12-MEDIUM' AS code, 'FABRIC BAND - 12 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 90555 AS id, 26684 AS group_id, 'FABRICBAND-03-13-MEDIUM' AS code, 'FABRIC BAND - 13 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 90574 AS id, 26655 AS group_id, 'FABRICBAND-03-LIMEYELLOW-MEDIUM' AS code, 'FABRIC BAND - LIMEYELLOW - MEDIUM' AS desired_name
    UNION ALL
    SELECT 90575 AS id, 26683 AS group_id, 'FABRICBAND-03-15-MEDIUM' AS code, 'FABRIC BAND - 15 - MEDIUM' AS desired_name
    UNION ALL
    SELECT 91587 AS id, 26667 AS group_id, 'FABRICBAND-03-PINK-LIGHT' AS code, 'FABRIC BAND - PINK - LIGHT' AS desired_name
    UNION ALL
    SELECT 91685 AS id, 26682 AS group_id, 'FABRICBAND-03-TEAL-HEAVY' AS code, 'FABRIC BAND - TEAL - HEAVY' AS desired_name
    UNION ALL
    SELECT 91686 AS id, 26656 AS group_id, 'FABRICBAND-03-MINT-HEAVY' AS code, 'FABRIC BAND - MINT - HEAVY' AS desired_name
    UNION ALL
    SELECT 91687 AS id, 26681 AS group_id, 'FABRICBAND-03-PURPLE-HEAVY' AS code, 'FABRIC BAND - PURPLE - HEAVY' AS desired_name
    UNION ALL
    SELECT 91688 AS id, 26680 AS group_id, 'FABRICBAND-03-PEACH-HEAVY' AS code, 'FABRIC BAND - PEACH - HEAVY' AS desired_name
    UNION ALL
    SELECT 91689 AS id, 26679 AS group_id, 'FABRICBAND-03-DARKGREY2-HEAVY' AS code, 'FABRIC BAND - DARKGREY2 - HEAVY' AS desired_name
    UNION ALL
    SELECT 91690 AS id, 26678 AS group_id, 'FABRICBAND-03-LIGHTGREY-HEAVY' AS code, 'FABRIC BAND - LIGHTGREY - HEAVY' AS desired_name
    UNION ALL
    SELECT 91691 AS id, 26677 AS group_id, 'FABRICBAND-03-DARKGREY-HEAVY' AS code, 'FABRIC BAND - DARKGREY - HEAVY' AS desired_name
    UNION ALL
    SELECT 91692 AS id, 26676 AS group_id, 'FABRICBAND-03-STRAWBERRY-HEAVY' AS code, 'FABRIC BAND - STRAWBERRY - HEAVY' AS desired_name
    UNION ALL
    SELECT 91693 AS id, 26675 AS group_id, 'FABRICBAND-03-FUSCHIA-MEDIUM' AS code, 'FABRIC BAND - FUSCHIA - MEDIUM' AS desired_name
    UNION ALL
    SELECT 91744 AS id, 26674 AS group_id, 'FABRICBAND-03-BLACK-HEAVY' AS code, 'FABRIC BAND - BLACK - HEAVY' AS desired_name
    UNION ALL
    SELECT 91745 AS id, 26662 AS group_id, 'FABRICBAND-03-NEONORANGE-MEDIUM' AS code, 'FABRIC BAND - NEONORANGE - MEDIUM' AS desired_name
    UNION ALL
    SELECT 91746 AS id, 26673 AS group_id, 'FABRICBAND-03-RED-MEDIUM' AS code, 'FABRIC BAND - RED - MEDIUM' AS desired_name
    UNION ALL
    SELECT 91748 AS id, 26671 AS group_id, 'FABRICBAND-03-MAUVE-HEAVY' AS code, 'FABRIC BAND - MAUVE - HEAVY' AS desired_name
    UNION ALL
    SELECT 91749 AS id, 26655 AS group_id, 'FABRICBAND-03-LIMEYELLOW-HEAVY' AS code, 'FABRIC BAND - LIMEYELLOW - HEAVY' AS desired_name
    UNION ALL
    SELECT 91750 AS id, 26673 AS group_id, 'FABRICBAND-03-RED-HEAVY' AS code, 'FABRIC BAND - RED - HEAVY' AS desired_name
    UNION ALL
    SELECT 91751 AS id, 26656 AS group_id, 'FABRICBAND-03-MINT-MEDIUM' AS code, 'FABRIC BAND - MINT - MEDIUM' AS desired_name
    UNION ALL
    SELECT 93935 AS id, 26673 AS group_id, 'FABRICBAND-03-RED-LIGHT' AS code, 'FABRIC BAND - RED - LIGHT' AS desired_name
    UNION ALL
    SELECT 93968 AS id, 26672 AS group_id, 'FABRICBAND-03-MATCHA-MEDIUM' AS code, 'FABRIC BAND - MATCHA - MEDIUM' AS desired_name
    UNION ALL
    SELECT 94438 AS id, 26666 AS group_id, 'FABRICBAND-03-BUTTERSCOTCH-MEDIUM' AS code, 'FABRIC BAND - BUTTERSCOTCH - MEDIUM' AS desired_name
    UNION ALL
    SELECT 94439 AS id, 26671 AS group_id, 'FABRICBAND-03-MAUVE-MEDIUM' AS code, 'FABRIC BAND - MAUVE - MEDIUM' AS desired_name
    UNION ALL
    SELECT 94440 AS id, 26670 AS group_id, 'FABRICBAND-03-LILAC-LIGHT' AS code, 'FABRIC BAND - LILAC - LIGHT' AS desired_name
    UNION ALL
    SELECT 94441 AS id, 26658 AS group_id, 'FABRICBAND-03-IVORY-LIGHT' AS code, 'FABRIC BAND - IVORY - LIGHT' AS desired_name
    UNION ALL
    SELECT 94442 AS id, 26669 AS group_id, 'FABRICBAND-03-FRUITPUNCH-LIGHT' AS code, 'FABRIC BAND - FRUITPUNCH - LIGHT' AS desired_name
    UNION ALL
    SELECT 98162 AS id, 26664 AS group_id, 'FABRICBAND-03-GREEN-MEDIUM' AS code, 'FABRIC BAND - GREEN - MEDIUM' AS desired_name
    UNION ALL
    SELECT 98163 AS id, 26669 AS group_id, 'FABRICBAND-03-FRUITPUNCH-MEDIUM' AS code, 'FABRIC BAND - FRUITPUNCH - MEDIUM' AS desired_name
    UNION ALL
    SELECT 98312 AS id, 26668 AS group_id, 'FABRICBAND-03-GREY-HEAVY' AS code, 'FABRIC BAND - GREY - HEAVY' AS desired_name
    UNION ALL
    SELECT 98313 AS id, 26667 AS group_id, 'FABRICBAND-03-PINK-HEAVY' AS code, 'FABRIC BAND - PINK - HEAVY' AS desired_name
    UNION ALL
    SELECT 98418 AS id, 26666 AS group_id, 'FABRICBAND-03-BUTTERSCOTCH-LIGHT' AS code, 'FABRIC BAND - BUTTERSCOTCH - LIGHT' AS desired_name
    UNION ALL
    SELECT 99117 AS id, 26665 AS group_id, 'FABRICBAND-03-TERRACOTTA-MEDIUM' AS code, 'FABRIC BAND - TERRACOTTA - MEDIUM' AS desired_name
    UNION ALL
    SELECT 101951 AS id, 26664 AS group_id, 'FABRICBAND-03-GREEN-HEAVY' AS code, 'FABRIC BAND - GREEN - HEAVY' AS desired_name
    UNION ALL
    SELECT 101952 AS id, 26660 AS group_id, 'FABRICBAND-03-BLUE-HEAVY' AS code, 'FABRIC BAND - BLUE - HEAVY' AS desired_name
    UNION ALL
    SELECT 101953 AS id, 26663 AS group_id, 'FABRICBAND-03-BABYPINK-MEDIUM' AS code, 'FABRIC BAND - BABYPINK - MEDIUM' AS desired_name
    UNION ALL
    SELECT 101954 AS id, 26662 AS group_id, 'FABRICBAND-03-NEONORANGE-LIGHT' AS code, 'FABRIC BAND - NEONORANGE - LIGHT' AS desired_name
    UNION ALL
    SELECT 101956 AS id, 26661 AS group_id, 'FABRICBAND-03-BABYBLUE-LIGHT' AS code, 'FABRIC BAND - BABYBLUE - LIGHT' AS desired_name
    UNION ALL
    SELECT 102027 AS id, 26660 AS group_id, 'FABRICBAND-03-BLUE-MEDIUM' AS code, 'FABRIC BAND - BLUE - MEDIUM' AS desired_name
) AS target ON target.id = i.id
SET i.name = target.desired_name
WHERE i.group_id = target.group_id
  AND i.code = target.code
  AND i.deleted_at IS NULL
  AND i.name <> target.desired_name;

UPDATE items
SET pcode = 'FABRICBAND-03'
WHERE id = 89310
  AND group_id = 26692
  AND code = 'FABRICBAND-03-07-MEDIUM'
  AND pcode = 'FABRICBAND-03-07-MEDIUM'
  AND deleted_at IS NULL;

-- In-transaction check: both leftover counts must be 0, pcode_fixed must be 1.
SELECT
    (SELECT COUNT(*) FROM item_group
      WHERE id IN (26655, 26656, 26658, 26659, 26660, 26661, 26662, 26663, 26664, 26665, 26666, 26667, 26668, 26669, 26670, 26671, 26672, 26673, 26674, 26675, 26676, 26677, 26678, 26679, 26680, 26681, 26682, 26683, 26684, 26685, 26686, 26687, 26688, 26689, 26690, 26691, 26692, 26693, 26694, 26695, 26696, 26697, 26698, 26699)
        AND name REGEXP '^FABRIC BAND (LIGHT|MEDIUM|HEAVY) - ') AS leftover_group_names,
    (SELECT COUNT(*) FROM items
      WHERE id IN (87097, 87098, 87099, 87107, 87108, 88672, 88673, 88674, 88675, 88676, 88677, 88678, 88679, 88680, 88681, 88682, 88683, 88704, 89310, 89311, 89312, 89313, 89314, 89315, 89316, 89317, 89318, 89319, 89320, 89321, 89386, 89396, 90553, 90554, 90555, 90574, 90575, 91587, 91685, 91686, 91687, 91688, 91689, 91690, 91691, 91692, 91693, 91744, 91745, 91746, 91748, 91749, 91750, 91751, 93935, 93968, 94438, 94439, 94440, 94441, 94442, 98162, 98163, 98312, 98313, 98418, 99117, 101951, 101952, 101953, 101954, 101956, 102027)
        AND name REGEXP '^FABRIC BAND (LIGHT|MEDIUM|HEAVY) - ') AS leftover_item_names,
    (SELECT COUNT(*) FROM items
      WHERE id = 89310 AND pcode = 'FABRICBAND-03') AS pcode_fixed;

COMMIT;

-- =============================================================================
-- C. Verify
-- =============================================================================

SELECT g.id, g.master, g.variant, g.name
FROM item_group AS g
WHERE g.id IN (26655, 26656, 26658, 26659, 26660, 26661, 26662, 26663, 26664, 26665, 26666, 26667, 26668, 26669, 26670, 26671, 26672, 26673, 26674, 26675, 26676, 26677, 26678, 26679, 26680, 26681, 26682, 26683, 26684, 26685, 26686, 26687, 26688, 26689, 26690, 26691, 26692, 26693, 26694, 26695, 26696, 26697, 26698, 26699)
ORDER BY g.variant;

SELECT i.id, i.group_id, i.pcode, i.code, i.name
FROM items AS i
WHERE i.id IN (87097, 87098, 87099, 87107, 87108, 88672, 88673, 88674, 88675, 88676, 88677, 88678, 88679, 88680, 88681, 88682, 88683, 88704, 89310, 89311, 89312, 89313, 89314, 89315, 89316, 89317, 89318, 89319, 89320, 89321, 89386, 89396, 90553, 90554, 90555, 90574, 90575, 91587, 91685, 91686, 91687, 91688, 91689, 91690, 91691, 91692, 91693, 91744, 91745, 91746, 91748, 91749, 91750, 91751, 93935, 93968, 94438, 94439, 94440, 94441, 94442, 98162, 98163, 98312, 98313, 98418, 99117, 101951, 101952, 101953, 101954, 101956, 102027)
ORDER BY i.id;

SELECT COUNT(*) AS parent_masters
FROM (
    SELECT master
    FROM item_group
    WHERE id IN (26655, 26656, 26658, 26659, 26660, 26661, 26662, 26663, 26664, 26665, 26666, 26667, 26668, 26669, 26670, 26671, 26672, 26673, 26674, 26675, 26676, 26677, 26678, 26679, 26680, 26681, 26682, 26683, 26684, 26685, 26686, 26687, 26688, 26689, 26690, 26691, 26692, 26693, 26694, 26695, 26696, 26697, 26698, 26699)
    GROUP BY master
) AS parents;
