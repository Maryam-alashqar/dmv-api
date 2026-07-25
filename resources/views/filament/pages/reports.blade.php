<x-filament-panels::page>
    <form>
        {{ $this->form }}
    </form>

    {{-- Revenue (FR-A20) --}}
    <x-filament::section>
        <x-slot name="heading">Revenue</x-slot>

        @php($revenue = $this->getRevenueStats())

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Revenue (selected period)</p>
                <p class="text-2xl font-semibold">${{ number_format($revenue['revenue'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">MRR (Monthly Recurring Revenue)</p>
                <p class="text-2xl font-semibold">${{ number_format($revenue['mrr'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Subscriptions Sold (selected period)</p>
                <p class="text-2xl font-semibold">{{ number_format($revenue['subscriptions_sold']) }}</p>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto">
            <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-300">
                Revenue by State
                <span class="text-xs font-normal text-gray-400">(based on each payer's current selected state, not necessarily the state active at time of payment)</span>
            </p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-2 pr-4">State</th>
                        <th class="py-2 pr-4">Payments</th>
                        <th class="py-2 pr-4">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getRevenueByState() as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4">{{ $row->name_en }} <span class="text-gray-400">({{ $row->name_ar }})</span></td>
                            <td class="py-2 pr-4">{{ $row->payments_count }}</td>
                            <td class="py-2 pr-4">${{ number_format($row->revenue, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-400">No revenue in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Usage (FR-A19) --}}
    <x-filament::section>
        <x-slot name="heading">Usage</x-slot>

        @php($usage = $this->getUsageStats())

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Active Users (selected period/state)</p>
                <p class="text-2xl font-semibold">{{ number_format($usage['active_users']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Simulation Attempts</p>
                <p class="text-2xl font-semibold">{{ number_format($usage['total_attempts']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Completion Rate</p>
                <p class="text-2xl font-semibold">{{ $usage['completion_rate'] }}%</p>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto">
            <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-300">Active Users by State</p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-2 pr-4">State</th>
                        <th class="py-2 pr-4">Active Users</th>
                        <th class="py-2 pr-4">Attempts</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getActiveUsersByState() as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4">{{ $row->name_en }} <span class="text-gray-400">({{ $row->name_ar }})</span></td>
                            <td class="py-2 pr-4">{{ $row->active_users }}</td>
                            <td class="py-2 pr-4">{{ $row->attempts }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-400">No activity in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Question performance (FR-A21) --}}
    <x-filament::section>
        <x-slot name="heading">Lowest-Performing Questions</x-slot>
        <x-slot name="description">Questions with the lowest correct-answer rate (minimum 3 answers), for targeted content review.</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-2 pr-4">Question</th>
                        <th class="py-2 pr-4">State</th>
                        <th class="py-2 pr-4">Category</th>
                        <th class="py-2 pr-4">Correct Rate</th>
                        <th class="py-2 pr-4">Answers</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getWorstQuestions() as $row)
                        @php($rate = $row->total_answers > 0 ? round($row->correct_answers / $row->total_answers * 100, 1) : 0)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4 max-w-md truncate" title="{{ $row->question_text_ar }}">{{ $row->question_text_ar }}</td>
                            <td class="py-2 pr-4">{{ $row->state_name }}</td>
                            <td class="py-2 pr-4">{{ $row->category_name }}</td>
                            <td class="py-2 pr-4">
                                <x-filament::badge :color="$rate < 40 ? 'danger' : ($rate < 70 ? 'warning' : 'success')">
                                    {{ $rate }}%
                                </x-filament::badge>
                            </td>
                            <td class="py-2 pr-4">{{ $row->total_answers }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-gray-400">Not enough answer data yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
