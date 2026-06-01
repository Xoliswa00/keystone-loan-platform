<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            NuPay Import Batches
        </h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg p-4">
            <table class="table-auto w-full border-collapse border border-gray-200">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-4 py-2">Import Ref</th>
                        <th class="border px-4 py-2">Row Count</th>
                        <th class="border px-4 py-2">Status</th>
                        <th class="border px-4 py-2">Created At</th>
                        <th class="border px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batches as $batch)
                        <tr>
                            <td class="border px-4 py-2">{{ $batch->import_ref }}</td>
                            <td class="border px-4 py-2">{{ $batch->row_count }}</td>
                            <td class="border px-4 py-2">{{ $batch->status }}</td>
                            <td class="border px-4 py-2">{{ $batch->created_at }}</td>
                            <td class="border px-4 py-2">
                                <a href="{{ route('nu-pay.import.show', $batch->import_ref) }}" class="bg-blue-500 text-white px-3 py-1 rounded">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4">
                {{ $batches->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
