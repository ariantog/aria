# Analysis of AddrBook / Customer Module
> [!IMPORTANT]
> **STATUS: DRAFT / ANALYSIS ONLY**. This plan is for review and analysis purposes. Do not execute changes without explicit approval.

## 1. Overview
The **AddrBook** module (managed by `AddrbookController`) handles the management of "Customers". In this system, a "Customer" is a polymorphic entity that can represent various types of contacts:
- Customer, Warehouse, Bank, Supplier, V. Warehouse, V. Account, Reseller, Account.

## 2. Existing Features
- **CRUD Operations**: Create, Read, Update, Delete for various Customer types.
- **Uniqueness Check**: Ensures Name + Birthdate combination is unique (optimization opportunity identified).
- **Member ID Generation**: Auto-generates `memberId` based on name and timestamp.
- **Statistics Integration**: Automatically tracks financial stats (balance, sell, buy, etc.) via `CustomerStat`.
- **Soft Deletes**: Supports restoring deleted customers.
- **Transaction Export**: Export transactions to CSV.
- **Item Export**: Export items related to customer (likely via transactions).

## 3. Codebase Structure

### Controller
- **File**: `refactor/app/Http/Controllers/AddrbookController.php`
- **Key Methods**: `postCreate`, `postEdit`, `postDelete` (with balance check), `checkExist`.
- **Refactoring Note**: Uses old-style `Input::get` commented out, replaced by `$request`. Mixed responsibility for all types.

### Models
- **Customer** (`refactor/app/Models/Customer.php`):
    - Table: `customers`
    - **Relationships**:
        - `stat()`: HasOne `CustomerStat` (1:1)
        - `locations()`: BelongsToMany `Location` (N:M)
        - `sentTransactions()`: HasMany `Transaction` (as Sender)
        - `receivedTransactions()`: HasMany `Transaction` (as Receiver)
        - `gajis()`: HasMany `Gajih` (Bank ID -> ID)
- **Transaction** (`refactor/app/Models/Transaction.php`):
    - Central hub connecting Customers and Items.
    - **Relationships**:
        - `sender()`: BelongsTo `Customer`
        - `receiver()`: BelongsTo `Customer`
        - `transactionDetail()`: HasMany `TransactionDetail`
- **Item** (`refactor/app/Models/Item.php`):
    - No direct link to Customer. Linked via `TransactionDetail` -> `Transaction` -> `Customer`.

### Helpers
- **CCManagerHelper** (`app/Helpers/CCManagerHelper.php`):
    - **Critical Path**: Calculates monthly statistics.
    - **Performance Risk**: `createStat` performs aggregation on `Transaction` table which can grow large.
- **DateHelper**: Utility for date formats.

## 4. Database Schema (Migration Notes)

### `customers` Table
| Column | Type | Notes |
| :--- | :--- | :--- |
| `id` | INT | Primary Key |
| `name` | VARCHAR | Index: `idx_customer_name` |
| `type` | INT | Index: `idx_customer_type` |
| `address` | TEXT | |
| `description` | TEXT | |
| `birthdate` | DATE | Used for uniqueness check |
| `memberId` | VARCHAR | Index: `idx_member_id` |
| `ppn` | BOOLEAN | |
| `is_online` | BOOLEAN | |
| `dummy` | BOOLEAN | (Inferred from usage) |
| `parent_id` | INT | (For Operation/Account hierarchy) |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |
| `deleted_at` | TIMESTAMP | Soft Deletes |

### `customerstat` Table
| Column | Type | Notes |
| :--- | :--- | :--- |
| `customer_id` | INT | PK, FK -> `customers.id` |
| `balance` | DOUBLE | Current running balance |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### `customer_class` Table (Stats History)
| Column | Type | Notes |
| :--- | :--- | :--- |
| `customer_id` | INT | Index together with `date` |
| `date` | DATE | Period (Month/Year) |
| `customer_type` | INT | |
| `sell`, `buy`, `return`... | DOUBLE | Aggregated metrics |

## 5. Optimization & Refactoring Plan

### A. Database Optimizations
1.  **Indexes**:
    - Add `(name, birthdate)` composite index on `customers` to speed up uniqueness checks.
    - Add `(customer_id, date)` index on `customer_class`.
    - Ensure `transactions` has indexes on `sender_id` and `receiver_id`.
2.  **Query Flow**:
    - `CCManagerHelper::createStat`: This recalculates stats from scratch by summing transactions. **Optimization**: Implement incremental updates or caching. Currently, every transaction insert triggers a stat update which is expensive.
    - `AddrbookController::checkExist`: Already noted to use `exists()`, ensure this is applied.

### B. Code Refactoring
1.  **Validation**: Move validation rules from Controller to `FormRequest` classes (e.g., `StoreCustomerRequest`, `UpdateCustomerRequest`).
2.  **Architecture**:
    - The `AddrbookController` handles too many types. Split logic into `CustomerService` or specialized controllers if logic diverges further.
    - Type Safety: Use PHP 8.1 Enums for `Customer::TYPE_*` constants.

## 6. Relationships Mapping
- **Customer -> Transaction**:
    - `Customer` (Sender) has many `Transaction` (Sales, Transfers Out).
    - `Customer` (Receiver) has many `Transaction` (Purchases, Transfers In).
- **Customer -> Item**:
    - Indirect: `Customer` -> `Transaction` -> `TransactionDetail` -> `Item`.
    - Used to track: "What items did this customer buy?" or "What items did this supplier provide?"

## 7. Migration Execution Notes
If recreating the tables is necessary, use the schema defined in Section 4. Ensure `customerstat` is created with a foreign key constraint to `customers` to cascade deletes (or handle via code as currently done).

> [!NOTE]
> This plan is pending user review. No code changes have been applied.
