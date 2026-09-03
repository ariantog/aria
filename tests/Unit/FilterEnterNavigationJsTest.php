<?php

it('submits GET filter forms on Enter instead of jumping to the next field', function () {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

    expect($layout)->toContain("if (el.matches('input, select')) {\n        return submitFilterForm(form);");
    expect($layout)->toContain('if (submitFilterForm(filterForm))');
    expect($layout)->not->toContain('return focusNextInFilterForm(form, el);');
    expect($layout)->not->toContain('focusNextInFilterForm(filterForm, input)');
});
