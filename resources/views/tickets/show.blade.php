<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Ticket detail</p>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $ticket->subject }}</h2>
            </div>
            <a href="{{ $user->role->isAgent() ? route('tickets.all') : route('tickets.my') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Back to tickets</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-700">
                        <span class="px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs">{{ $ticket->category->label() }}</span>
                        <span class="px-2 py-1 rounded-full bg-gray-200 text-gray-800 text-xs">Status: {{ $ticket->status->label() }}</span>
                        <span class="px-2 py-1 rounded-full bg-amber-100 text-amber-700 text-xs">Severity {{ $ticket->severity }}</span>
                        <span class="text-gray-500">Created {{ $ticket->created_at->format('Y-m-d H:i') }}</span>
                        <span class="text-gray-500">Updated {{ $ticket->updated_at->format('Y-m-d H:i') }}</span>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold text-gray-900">Description</h3>
                        <p class="text-gray-700 whitespace-pre-line">{{ $ticket->description }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                        <div>
                            <div class="text-gray-500">Created by</div>
                            <div class="font-medium">{{ $ticket->creator?->name }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Assigned to</div>
                            <div class="font-medium">{{ $ticket->assignee?->name ?? 'Unassigned' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Attachments</h3>
                    @if($user->role->isEmployee())
                        <span class="text-sm text-gray-500">Upload attachments when creating a ticket.</span>
                    @endif
                </div>
                <div class="p-6">
                    @if($ticket->attachments->isEmpty())
                        <p class="text-sm text-gray-500">No attachments added.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach($ticket->attachments as $attachment)
                                <li class="flex items-center justify-between text-sm text-gray-700">
                                    <div>
                                        <div class="font-medium">{{ $attachment->original_filename }}</div>
                                        <div class="text-xs text-gray-500">{{ number_format($attachment->size / 1024, 1) }} KB • uploaded {{ $attachment->created_at->format('Y-m-d H:i') }}</div>
                                    </div>
                                    <a href="{{ route('attachments.download', $attachment) }}" class="text-indigo-600 hover:text-indigo-500">Download</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            @if($user->role->isAgent())
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Agent Actions</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <label class="block text-sm font-medium text-gray-700">Assignment</label>
                            <div class="flex items-center gap-3">
                                <select name="assigned_to" class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Unassigned</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" @selected($ticket->assignee?->id === $agent->id)>{{ $agent->name }} ({{ $agent->email }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium shadow hover:bg-indigo-500">Update</button>
                            </div>
                            <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
                        </form>

                        <form method="POST" action="{{ route('tickets.update-status', $ticket) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <div class="flex items-center gap-3">
                                <select name="status" class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->value }}" @selected($ticket->status === $status)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium shadow hover:bg-indigo-500">Set Status</button>
                            </div>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </form>
                    </div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg" x-data="similarForTicket()">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Similar Active Tickets</h3>
                        <button type="button" @click="checkSimilar()" class="text-sm text-indigo-600 hover:text-indigo-500">Find similar</button>
                    </div>
                    <div class="p-6" x-show="loading">
                        <p class="text-sm text-gray-600">Checking for similar tickets…</p>
                    </div>
                    <div class="p-6" x-show="!loading && suggestions.length === 0">
                        <p class="text-sm text-gray-500">No similar tickets found.</p>
                    </div>
                    <div class="p-6 space-y-4" x-show="suggestions.length > 0">
                        <template x-for="s in suggestions" :key="s.id">
                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold text-gray-900" x-text="s.subject"></div>
                                    <div class="text-xs text-gray-600">Relevance: <span class="font-semibold" x-text="(s.relevance_score ?? 0).toFixed(2)"></span></div>
                                </div>
                                <div class="flex items-center gap-3 text-sm text-gray-600 mt-1">
                                    <span class="px-2 py-1 rounded-full bg-indigo-100 text-indigo-700" x-text="s.category"></span>
                                    <span class="px-2 py-1 rounded-full bg-gray-200 text-gray-700" x-text="s.status"></span>
                                    <span x-text="new Date(s.created_at).toLocaleDateString()"></span>
                                </div>
                                <p class="text-sm text-gray-700 mt-2" x-text="s.description_snippet"></p>
                                <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div class="text-xs text-gray-600">
                                        <div>Subject score: <span class="font-semibold" x-text="(s.subject_score ?? 0).toFixed(2)"></span></div>
                                        <div class="mt-1 flex flex-wrap gap-1" x-show="(s.matched_tokens?.subject?.length || 0) > 0">
                                            <template x-for="t in s.matched_tokens.subject" :key="t">
                                                <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-700" x-text="t"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <div>Description score: <span class="font-semibold" x-text="(s.description_score ?? 0).toFixed(2)"></span></div>
                                        <div class="mt-1 flex flex-wrap gap-1" x-show="(s.matched_tokens?.description?.length || 0) > 0">
                                            <template x-for="t in s.matched_tokens.description" :key="t">
                                                <span class="px-2 py-1 rounded-full bg-amber-100 text-amber-700" x-text="t"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <a :href="'/tickets/' + s.id + '/detail'" class="text-xs text-indigo-600 hover:text-indigo-500">View ticket</a>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="p-6" x-show="error">
                        <p class="text-sm text-red-600" x-text="error"></p>
                    </div>
                </div>
                <script>
                    function similarForTicket() {
                        return {
                            loading: false,
                            suggestions: [],
                            error: null,
                            async checkSimilar() {
                                this.loading = true;
                                this.error = null;
                                try {
                                    const response = await fetch('{{ route('tickets.similar') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                        },
                                        body: JSON.stringify({
                                            subject: '{{ addslashes($ticket->subject) }}',
                                            description: {!! json_encode($ticket->description) !!},
                                            category: '{{ $ticket->category->value }}',
                                        }),
                                    });
                                    if (!response.ok) {
                                        throw new Error('Similarity check failed');
                                    }
                                    this.suggestions = await response.json();
                                } catch (e) {
                                    this.error = 'Unable to fetch similar tickets right now.';
                                } finally {
                                    this.loading = false;
                                }
                            }
                        }
                    }
                </script>
            @endif
        </div>
    </div>
</x-app-layout>
