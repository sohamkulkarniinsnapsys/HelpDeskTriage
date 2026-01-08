@php use Illuminate\Support\Str; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Browse tickets scoped to your role.</p>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $context === 'all' ? 'All Tickets' : 'My Tickets' }}
                </h2>
            </div>
            @if($context === 'my')
                <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium shadow hover:bg-indigo-500">Create Ticket</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search subject or description" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />

                    <select name="status" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>

                    <select name="category" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->value }}" @selected($filters['category'] === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>

                    <select name="severity" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Severity</option>
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}" @selected((string) $filters['severity'] === (string) $i)>Severity {{ $i }}</option>
                        @endfor
                    </select>

                    <select name="per_page" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="10" @selected((int) $filters['per_page'] === 10)>10 per page</option>
                        <option value="25" @selected((int) $filters['per_page'] === 25)>25 per page</option>
                        <option value="50" @selected((int) $filters['per_page'] === 50)>50 per page</option>
                    </select>

                    @if($user->role->isAgent() && $context === 'all')
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input type="checkbox" name="unassigned" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked($filters['unassigned'])>
                            <span class="ml-2">Unassigned only</span>
                        </label>
                    @endif

                    <div class="md:col-span-6 flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium shadow hover:bg-indigo-500">Apply</button>
                        <a href="{{ request()->url() }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    @if($tickets->isEmpty())
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-500">No tickets found with the current filters.</p>
                            @if($context === 'my')
                                <a href="{{ route('tickets.create') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Create a ticket</a>
                            @else
                                <a href="{{ request()->url() }}" class="text-sm text-indigo-600 hover:text-indigo-500">Clear filters</a>
                            @endif
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <th class="px-4 py-3">Subject</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Severity</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Creator</th>
                                        <th class="px-4 py-3">Assignee</th>
                                        <th class="px-4 py-3">Created</th>
                                        <th class="px-4 py-3">Attachments</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($tickets as $ticket)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <a href="{{ route('tickets.view', $ticket) }}" class="text-indigo-700 hover:underline font-medium">{{ $ticket->subject }}</a>
                                                @if($ticket->description)
                                                    <p class="text-sm text-gray-500 truncate">{{ Str::limit($ticket->description, 120) }}</p>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs">{{ $ticket->category->label() }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="text-sm text-gray-700">{{ $ticket->severity }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-800 text-xs">{{ $ticket->status->label() }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->creator?->name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $ticket->created_at->format('Y-m-d') }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->attachments_count }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="pt-4">
                            {{ $tickets->withQueryString()->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
