<?php

it('ships combobox typeahead helpers in the app layout', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

    expect($layout)->toContain('function comboboxFindByPrefix(items, startIndex, prefix)');
    expect($layout)->toContain('function comboboxApplyTypeahead(items, activeIndex, prefix, key)');
    expect($layout)->toContain('if (this.open && len > 0 && isPrintableComboboxKey(key, e))');
    expect($layout)->toContain('if (this._applyTypeahead(key))');
});

it('jumps async combobox options when typing while the dropdown is open', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

    preg_match('/function comboboxItemLabel\(item\) \{.*?\n\}/s', $layout, $labelFn);
    preg_match('/function comboboxFindByPrefix\(items, startIndex, prefix\) \{.*?\n\}/s', $layout, $findFn);
    preg_match('/function comboboxApplyTypeahead\(items, activeIndex, prefix, key\) \{.*?\n\}/s', $layout, $applyFn);

    expect($labelFn)->not->toBeEmpty();
    expect($findFn)->not->toBeEmpty();
    expect($applyFn)->not->toBeEmpty();

    $script = $labelFn[0]."\n".$findFn[0]."\n".$applyFn[0].<<<'JS'

const items = [
    { name: 'Alpha Supply' },
    { name: 'Beta Warehouse' },
    { name: 'Gamma Store' },
];

let first = comboboxApplyTypeahead(items, -1, '', 'b');
if (first.index !== 1 || !first.handled) {
    process.exit(1);
}

let wrapped = comboboxApplyTypeahead(items, 1, '', 'g');
if (wrapped.index !== 2 || !wrapped.handled) {
    process.exit(2);
}

let cumulative = comboboxApplyTypeahead(items, -1, 'be', 't');
if (cumulative.index !== 1 || cumulative.prefix !== 'bet' || !cumulative.handled) {
    process.exit(3);
}

process.stdout.write('ok');
JS;

    $output = shell_exec('node -e '.escapeshellarg($script));

    expect(trim((string) $output))->toBe('ok');
});

it('uses typeahead for item name rows in transaction create', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/transactions/create.blade.php');

    expect($view)->toContain('applyRowTypeahead(row, key)');
    expect($view)->toContain('if (row.showDropdown && len > 0 && isPrintableComboboxKey(key, e))');
});
