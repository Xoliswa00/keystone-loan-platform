<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customers') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="customersTable()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @foreach (['success', 'error'] as $msg)
                @if(session($msg))
                    <div class="mb-4 px-4 py-2 rounded {{ $msg === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ session($msg) }}
                    </div>
                @endif
            @endforeach

        <div class="bg-white shadow-sm sm:rounded-lg p-6">

    {{-- Search & Add --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-2">
        <input 
            type="text" 
            placeholder="Search by name, email, phone, or code..." 
            x-model="search"
            class="border-gray-300 rounded-md shadow-sm px-4 py-2 w-full sm:w-80 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
       <!-- <a href="{{ route('customers.create') }}" 
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition shadow-md">
            + Add Customer
        </a>-->
    </div>

    {{-- Responsive Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 border rounded-lg table-auto">
            <thead class="bg-gray-50 sticky top-0">
                <tr>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">#</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">Name</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">Email</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">Phone</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">Code</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">Balance</th>
                    <th class="px-4 py-2 text-right text-sm font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2" x-html="highlight('{{ $customer->id }}')"></td>
                        <td class="px-4 py-2" x-html="highlight('{{ $customer->user->name }}')"></td>
<td class="px-4 py-2"
    x-html="highlight('<a href=\'mailto:{{ $customer->user->email }}\' class=\'text-blue-600 underline\'>{{ $customer->user->email }}</a>')">
</td>
                        <td class="px-4 py-2 flex items-center gap-2">
                          @php
    $rawPhone = $customer->user->phone;

    // Remove spaces, dashes, brackets, etc.
    $phone = preg_replace('/\D/', '', $rawPhone);

    // If starts with 0 → replace with 27
    if (str_starts_with($phone, '0')) {
        $phone = '27' . substr($phone, 1);
    }

    // If starts with +27 (already intl but with +)
    if (str_starts_with($phone, '27') === false && strlen($phone) === 9) {
        $phone = '27' . $phone;
    }

    $whatsMessage = urlencode("Hi {$customer->user->name},");
@endphp

<a href="https://wa.me/{{ $phone }}?text={{ $whatsMessage }}"
   target="_blank"
   class="text-green-600 hover:underline flex items-center gap-1">
    {{ $customer->user->phone }}
</a>

                        </td>
                        <td class="px-4 py-2" x-html="highlight('{{ $customer->customer_code }}')"></td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded-full {{ $customer->current_balance > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                {{ number_format($customer->current_balance, 2) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div x-data="{ open: false }" class="relative inline-block text-left">
                                <button @click="open = !open" type="button" class="inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-3 py-1 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none">
                                    Actions
                                    <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div 
                                    x-show="open" 
                                    @click.outside="open = false"
                                    x-transition
                                    class="origin-top-right absolute right-0 mt-2 w-36 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                                >
                                    <div class="py-1">
                                        <a href="{{ route('customers.show', $customer) }}" class="block px-4 py-2 text-sm text-blue-600 hover:bg-gray-100">View</a>
                                       <!--- <a href="{{ route('customers.edit', $customer) }}" class="block px-4 py-2 text-sm text-green-600 hover:bg-gray-100">Edit</a>
                                        <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('Are you sure?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Delete</button>
                                        </form>-->
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-gray-500 py-4">
                            No customers found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</div>

        </div>
    </div>

    <script>
        function customersTable() {
            return {
                search: '',
                highlight(text) {
                    if(!this.search) return text;
                    const regex = new RegExp(`(${this.search})`, 'gi');
                    return text.replace(regex, `<span class="bg-yellow-200">$1</span>`);
                }
            }
        }
    </script>
</x-app-layout>
