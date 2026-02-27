DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=core
DB_USERNAME=root
DB_PASSWORD=# Analysis: Item Module Refactoring

## 1. Overview
This document analyzes the current state of the Item module (Models, Controllers, Helpers, Database) and outlines a plan for refactoring and optimization.
The goal is to improve maintainability, performance, and scalability.

## 2. Current Architecture

### Models
- **`Item`**: The core model. Contains hardcoded constants for `Brands` and `Types`. logic for naming and image paths is embedded in the model.
- **`ItemGroup`**: Represents a group of items (likely variants like size/color).
- **`ItemTag`**: Pivot model for `items` and `tags`.
- **`Tag`**: General purpose tag model (used for Size, Color, Type, Jahit).
- **`WarehouseItem`**: Manages stock quantity per warehouse.

### Controllers
- **`ItemsController`**: Handles web requests for listing, creating, editing, and viewing items.
    - **Issues**:
        - Contains mixed logic (View rendering + some business logic).
        - Direct usage of `ItemsManagerHelper` which is a "god class".
        - Search logic uses `LIKE %...%` which is slow on large datasets.
        - Raw SQL queries for statistics.

### Warehouse Relationship
- **Current Implementation**:
    - Warehouses are stored in the `customers` table with `type = 2` (`Customer::TYPE_WAREHOUSE`).
    - `WarehouseItem` is the pivot table containing `quantity`.
    - `ItemsManagerHelper` handles stock adjustments (`add`, `deduct`).
- **Issues**:
    - **Ambiguity**: `Customer` model is overloaded. It represents Customers, Warehouses, Banks, Suppliers, etc.
    - **Lack of Encapsulation**: Stock logic is scattered. `WarehouseItem` is accessed directly in controllers.
- **Refactoring Strategy**:
    - **Explicit Concept**: Introduce a `Warehouse` class (extending `Customer` or using a global scope) to encapsulate warehouse-specific logic.
    - **Service Layer**: `InventoryService` should handle all interactions between `Item` and `Warehouse`. No direct `WarehouseItem` manipulation in controllers.

#### 1. ItemsManagerHelper
This is a "God Class" that acts as a catch-all for Item-related logic.
- **Responsibilities**:
    - **Item Creation (`createItems`, `createCrystalItem`)**: Complex logic to parse `pcode`, determine brands, create `Item` and `ItemGroup`, and generate `ItemTag` links.
    - **Stock Management (`add`, `deduct`)**: Directly modifies `WarehouseItem` records.
    - **Image Processing (`saveImage`)**: Handles file uploads and resizing using `Intervention\Image`.
    - **Tag Loading (`loadTags`, `loadTagsJSON`)**: Caches and formats tags for the UI.
- **Issues**:
    - **Duplicated Logic**: Brand parsing logic exists here and in `Item` model.
    - **Hardcoded Business Rules**: The `pcode` regex and `type` dependent logic are hardcoded.
    - **No Transaction Management**: Methods like `add`/`deduct` don't enforce transactions, relying on the caller.

#### 2. LocalHelper
This class is essentially a configuration file disguised as a Helper.
- **Content**: A single static array `$var` containing paths, URLs, and default values.
- **Issues**:
    - **Hardcoded Paths**: Contains server-specific paths (e.g., `/home/crystalsports/www//cdn/img/items/`) which may not match the current environment (Windows/Laragon).
    - **Global State**: Static property access makes testing difficult.
- **Refactoring**:
    - Move these values to Laravel's `config/` files (e.g., `config/core-nation.php`).
    - Use `config('core-nation.item_image_path')` instead of `LocalHelper::$var['item_image_path']`.

## 3. Database Schema (Inferred)

Based on models and usage:

- **`items`**
    - `id` (PK)
    - `group_id` (FK -> item_groups.id)
    - `name`, `code`, `pcode`
    - `brand` (integer, mapped to constants)
    - `type` (integer, mapped to constants)
    - `price`, `cost`
    - `tag_ids` (text/varchar, comma-separated attributes - Denormalized)
    - `jubelio_item_id` (External integration)
    - `created_at`, `updated_at`

- **`item_groups`**
    - `id` (PK)
    - `name`, `description`, `description2`, `alias`, `master`, `variant`
    - `created_at`, `updated_at`

- **`tags`**
    - `id` (PK)
    - `name`, `code`, `type` (integer)

- **`item_tag`**
    - `item_id` (FK)
    - `tag_id` (FK)

- **`warehouse_item`**
    - `id` (PK)
    - `item_id` (FK)
    - `warehouse_id` (FK)
    - `quantity`

## 4. Identified Issues & Bottlenecks

1.  **N+1 Queries**:
    - `Item::getQtyWarehouse` executes a query for every item call.
    - Loop logic in `ItemsController` and `ItemsManagerHelper` often queries DB inside loops.
2.  **Hardcoded Values**:
    - Brands (0-20) and Types (1, 2, 3, 5) are hardcoded in `Item.php`. Hard to maintain.
3.  **Complex "Helper"**:
    - `ItemsManagerHelper` is critical but fragile. It handles image resizing, transaction limits, and stock logic all in one place.
4.  **Search Performance**:
    - Queries use `OR` conditions and leading wildcards (`%query%`). This prevents index usage.
5.  **Tag System**:
    - `tag_ids` column in `items` table is a comma-separated string. This violates 1NF and makes querying hard (though `item_tag` table exists, the string is used for logic).
6.  **"Aset Lancar" Logic**:
    - Mixed into the general `Item` model with `switch` statements. Should perhaps be a separate logic flow or subclass.

## 5. Proposed Refactoring Plan

### A. Infrastructure
1.  **Enums**: Create PHP Enums for `ItemType` and `ItemBrand` (if PHP 8.1+) or Class Constants in a dedicated class.
2.  **Service Layer**:
    - `ItemService`: Handle CRUD, `pcode` parsing, naming logic.
    - `InventoryService`: Handle `WarehouseItem` logic (add/deduct stock).
    - `ImageService`: Handle image upload, resizing, and path generation.
    - `TagService`: Handle tag sync and retrieval.

### B. Database Optimizations
1.  **Indexes**: Ensure indexes on `items(code)`, `items(pcode)`, `items(group_id)`, `warehouse_item(item_id, warehouse_id)`.
2.  **Scopes**: Move search logic to Local Scopes (`scopeSearch`, `scopeFilterByTags`).

### C. Code Refactoring steps
1.  **Refactor LocalHelper**:
    - Create `config/core-nation.php`.
    - Move `$var` array to this config file.
    - Replace all `LocalHelper::$var[...]` usages with `config('core-nation.key')`.
2.  **Extract Stock Logic**: Move `add`/`deduct` from `ItemsManagerHelper` to `InventoryService`.
3.  **Extract Image Logic**: Move `saveImage` from `ItemsManagerHelper` to `ImageService`.
4.  **Refactor Create/Update**:
    - Create `ItemService` to handle `createItems` and `updateItem` logic.
    - Extract `pcode` parsing and brand logic into strictly typed methods or Value Objects.
    - Remove `ItemsManagerHelper`.
4.  **Refactor Controller**:
    - Inject Services.
    - Use `FormRequests` for validation.
    - Use `Resources` for API responses (if JSON is returned).

## 6. Specific Analysis: "Aset Lancar" & "Sistem Group"
- **Aset Lancar**: Currently treated as `Item` with `type = 2`. Logic involves skipping some brand/naming generation.
    - **Recommendation**: Keep as `type` but isolate logic in Service. e.g. `ItemStrategy` pattern or simple if-else in Service, removing it from Model.
- **Sistem Group**: `ItemGroup` links variants. `pcode` determines group (e.g. `CA12345/01` where `CA12345` is group).
    - **Optimization**: Ensure `pcode` parsing is robust. The auto-creation of groups in `createItems` is implicit. Make it explicit in `ItemService`.

### D. Warehouse Integration
1.  **Warehouse Model**:
    - Create `App\Models\Warehouse` extending `App\Models\Customer`.
    - Apply Global Scope `where('type', Customer::TYPE_WAREHOUSE)`.
    - Add relationship `items()` returning `BelongsToMany` through `warehouse_item`.
2.  **Inventory Service**:
    - Ensure `addStock(Warehouse $warehouse, Item $item, $qty)` and `deductStock` methods are robust.
    - Validate that the `customer` passed is indeed a `warehouse`.
