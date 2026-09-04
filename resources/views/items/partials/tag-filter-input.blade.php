<input type="search"
       x-model="tagFilters.{{ $field }}"
       @input="filterTagField('{{ $field }}')"
       placeholder="{{ $placeholder }}"
       autocomplete="off"
       class="mb-1.5 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
       data-testid="tag-filter-{{ $field }}">
