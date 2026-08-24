<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm @if(!empty($retired)) opacity-75 @endif">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">PKP</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Banks</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($entities as $entity)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">
                    {{ $entity->name }}
                    @if(!empty($retired))
                        <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Retired</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($entity->is_pkp)
                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">PKP</span>
                    @else
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Non-PKP</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($entity->banks->isEmpty())
                        <span class="text-gray-400">None assigned</span>
                    @else
                        <ul class="flex flex-wrap gap-1.5">
                            @foreach($entity->banks as $bank)
                            <li class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $bank->name }}</li>
                            @endforeach
                        </ul>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('reports.entities.edit', $entity) }}" class="text-blue-600 hover:underline">Edit</a>
                </td>
            </tr>
            @empty
            @if($emptyMessage)
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
            @endif
            @endforelse
        </tbody>
    </table>
</div>
