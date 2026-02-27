# Implementation Plan - Item Module Refactoring

The goal of this refactoring is to modernize the `Item` module, improve performance, and increase maintainability by breaking down the `ItemsManagerHelper` god class and optimizing database interactions.

## User Review Required

> [!IMPORTANT]
> **Database Indexes**: I will be adding indexes to `items` and `warehouse_item` tables to improve performance.
> **Logic Change**: The `Aset Lancar` logic currently mixed in `Item` model will be isolated in the Service layer, but the data structure remains the same.

## Proposed Changes

### Infrastructure & Enums

#### [NEW] [ItemBrand.php](file:///c:/laragon/www/core-nation-2/app/Enums/ItemBrand.php)
- Create Enum for Item Brands (replacing `Item::BRAND_XX` constants).

#### [NEW] [ItemType.php](file:///c:/laragon/www/core-nation-2/app/Enums/ItemType.php)
- Create Enum for Item Types (replacing `Item::TYPE_XX` constants).

#### [NEW] [Warehouse.php](file:///c:/laragon/www/core-nation-2/app/Models/Warehouse.php)
- Create `Warehouse` model extending `Customer`.
- Add `booted` method with `addGlobalScope` for `type = 2`.
- Add relationship `items` (BelongsToMany via `warehouse_item`).

### Configuration & Helpers

#### [NEW] [config/core-nation.php](file:///c:/laragon/www/core-nation-2/config/core-nation.php)
- Migrate `LocalHelper::$var` contents here.
- Define `item_image_path`, `default_warehouse`, etc.

#### [DELETE] [LocalHelper.php](file:///c:/laragon/www/core-nation-2/app/Helpers/LocalHelper.php)
- Remove after migrating all usages to `config()`.

### Services (Logic Extraction)

#### [NEW] [ItemService.php](file:///c:/laragon/www/core-nation-2/app/Services/ItemService.php)
- Handle Item Creation and Update logic.
- Handle `pcode` parsing and `code` generation.
- Handle Group auto-creation/assignment.

#### [NEW] [InventoryService.php](file:///c:/laragon/www/core-nation-2/app/Services/InventoryService.php)
- Handle Stock additions and deductions (`WarehouseItem`).
- Move logic from `ItemsManagerHelper::add` and `deduct`.
- Accept `Warehouse` model instance to ensure type safety.
- **Method**: `adjustStock(Warehouse $warehouse, Item $item, float $quantity, string $reason)`

#### [NEW] [ImageService.php](file:///c:/laragon/www/core-nation-2/app/Services/ImageService.php)
- Handle Image upload, resizing, and path generation (Intervention Image logic).

### Model Refactoring

#### [MODIFY] [Item.php](file:///c:/laragon/www/core-nation-2/app/Models/Item.php)
- Remove hardcoded constants (use Enums).
- Add Local Scopes: `scopeSearch($query, $term)`, `scopeFilterByTags($query, $tags)`.
- Cleanup logic dependent on `type` where possible.

#### [MODIFY] [ItemsController.php](file:///c:/laragon/www/core-nation-2/app/Http/Controllers/ItemsController.php)
- Inject `ItemService` and `InventoryService`.
- Replace `ItemsManagerHelper` usage with Service calls.
- Replace `LocalHelper::$var` usage with `config()`.
- Use `DB::transaction` around Service calls.

### Clean Up

#### [DELETE] [ItemsManagerHelper.php](file:///c:/laragon/www/core-nation-2/app/Helpers/ItemsManagerHelper.php)
- Once all logic is migrated, this file will be removed.
- **Interim Step**: If strict removal is too risky, deprecate methods and have them call the new Service classes.

### Database

#### [NEW] [2024_02_14_000000_add_indexes_to_items_table.php](file:///c:/laragon/www/core-nation-2/database/migrations/2024_02_14_000000_add_indexes_to_items_table.php)
- Add index on `items(code)`.
- Add index on `items(pcode)`.
- Add index on `items(group_id)`.
- Add index on `warehouse_item(item_id, warehouse_id)`.

## Verification Plan

### Automated Tests (New)
Since no existing tests cover this module, I will create new Unit Tests for the Services.

1.  **ItemServiceTest**:
    - Test `pcode` parsing (Standard Item vs Aset Lancar).
    - Test Creation of Item creates correct `code` and `name`.
    - Run: `php artisan test --filter ItemServiceTest`

3.  **InventoryServiceTest**:
    - Test stock addition (creates `WarehouseItem` if not exists).
    - Test stock deduction (handling negative stock if allowed/disallowed).
    - Test `adjustStock` validation (ensure valid Warehouse).
    - Run: `php artisan test --filter InventoryServiceTest`

### Manual Verification
1.  **List Items**:
    - Go to `/items` (or equivalent route).
    - Test Search by Name, Code, and Group.
    - Status: Should load faster and filters should work.
2.  **Create Item**:
    - Go to `/items/create`.
    - Create a Standard Item (Type Item, Select Size/Color).
    - Verify Item created in DB with correct `code` and `group`.
3.  **Update Item**:
    - Edit an item, change tags/description.
    - Verify changes saved.
4.  **Stock Change**:
    - Trigger a stock change (e.g. Transaction or Manual Adjustment).
    - Verify `warehouse_item` quantity updates.
