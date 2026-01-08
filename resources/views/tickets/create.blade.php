<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Create a new helpdesk ticket</p>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Ticket</h2>
            </div>
            <a href="{{ route('tickets.my') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Back to My Tickets</a>
        </div>
    </x-slot>

    <div class="py-8" x-data="ticketComposer()">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="p-6 space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                            <input id="subject" name="subject" type="text" x-model="subject" @input="debouncedCheck" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value="{{ old('subject') }}" required>
                            <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="category" name="category" x-model="category" @change="debouncedCheck" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Select a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category')" class="mt-2" />
                        </div>

                        <div>
                            <label for="severity" class="block text-sm font-medium text-gray-700">Severity (1 = low, 5 = high)</label>
                            <select id="severity" name="severity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                @foreach ($severities as $severity)
                                    <option value="{{ $severity }}" @selected((int) old('severity', 3) === $severity)>Severity {{ $severity }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('severity')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="description" name="description" rows="6" x-model="description" @input="debouncedCheck" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Attachments</label>
                        <input type="file" name="attachments[]" multiple class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.gif">
                        <p class="text-xs text-gray-500">Up to 5 files, 10MB each. Accepted: pdf, doc, docx, xls, xlsx, txt, jpg, jpeg, png, gif.</p>
                        <x-input-error :messages="$errors->get('attachments')" class="mt-2" />
                        <x-input-error :messages="$errors->get('attachments.*')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <span class="inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                            Draft saves are not automatic; submit to create the ticket.
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Submit Ticket
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg" x-data>
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Similarity suggestions update as you type.</p>
                        <h3 class="font-semibold text-lg text-gray-800">Top Similar Tickets</h3>
                    </div>
                    <button type="button" @click="manualCheck" class="text-sm text-indigo-600 hover:text-indigo-500">Check Similar Tickets</button>
                </div>

                <div class="p-6" x-show="loading">
                    <p class="text-sm text-gray-600">Checking for similar tickets…</p>
                </div>

                <div class="p-6" x-show="!loading && suggestions.length === 0">
                    <p class="text-sm text-gray-500">No similar tickets yet. Start typing a subject or description.</p>
                </div>

                <div class="p-6 space-y-4" x-show="suggestions.length > 0">
                    <template x-for="ticket in suggestions" :key="ticket.id">
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="font-semibold text-gray-900" x-text="ticket.subject"></div>
                                <div class="text-xs text-gray-600">Relevance: <span class="font-semibold" x-text="(ticket.relevance_score ?? 0).toFixed(2)"></span></div>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-gray-600 mt-1">
                                <span class="px-2 py-1 rounded-full bg-indigo-100 text-indigo-700" x-text="ticket.category"></span>
                                <span class="px-2 py-1 rounded-full bg-gray-200 text-gray-700" x-text="ticket.status"></span>
                                <span x-text="new Date(ticket.created_at).toLocaleDateString()"></span>
                            </div>
                            <p class="text-sm text-gray-700 mt-2" x-text="ticket.description_snippet"></p>
                        </div>
                    </template>
                </div>

                <div class="p-6" x-show="error">
                    <p class="text-sm text-red-600" x-text="error"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function ticketComposer() {
            return {
                subject: @json(old('subject', '')),
                description: @json(old('description', '')),
                category: @json(old('category', '')),
                loading: false,
                suggestions: [],
                error: null,
                debounceTimer: null,
                debouncedCheck() {
                    clearTimeout(this.debounceTimer);
                    this.debounceTimer = setTimeout(() => this.checkSimilar(), 400);
                },
                manualCheck() {
                    this.checkSimilar(true);
                },
                async checkSimilar(force = false) {
                    if (!force && !this.subject && !this.description) {
                        this.suggestions = [];
                        this.error = null;
                        return;
                    }

                    this.loading = true;
                    this.error = null;

                    try {
                        const response = await fetch(@json(route('tickets.similar')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            },
                            body: JSON.stringify({
                                subject: this.subject,
                                description: this.description,
                                category: this.category || null,
                            }),
                        });

                        if (!response.ok) {
                            throw new Error('Similarity check failed');
                        }

                        this.suggestions = await response.json();
                    } catch (error) {
                        this.error = 'Unable to fetch similar tickets right now.';
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
