<input type="hidden" name="tab" value="{{ $tab }}">
<input type="hidden" name="item_type" value="{{ $itemTypeQuery }}">
<input type="hidden" name="sales_window" value="{{ $salesWindowQuery }}">
<input type="hidden" name="from" value="{{ request()->query('from') }}">
<input type="hidden" name="to" value="{{ request()->query('to') }}">
<input type="hidden" name="warehouse_id" value="{{ request()->query('warehouse_id', $healthWarehouseId ?? '') }}">
<input type="hidden" name="page" value="{{ request()->query('page') }}">
