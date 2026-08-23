<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">1. Upload a spreadsheet</x-slot>
        <x-slot name="description">
            Excel (.xlsx) or CSV, with one question per row.
            <button type="button" wire:click="downloadTemplate" class="text-primary-600 hover:underline">Download the template file</button>
            to see the exact columns expected.
        </x-slot>

        <form wire:submit="parseFile">
            {{ $this->form }}

            <div class="mt-4">
                <input type="file" wire:model="uploadedFile" accept=".xlsx,.xls,.csv"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                <div wire:loading wire:target="uploadedFile" class="mt-1 text-xs text-gray-500">Uploading…</div>
                @if ($uploadedFile)
                    <p class="mt-1 text-xs text-gray-500">Selected: {{ $uploadedFile->getClientOriginalName() }}</p>
                @endif
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="parseFile">
                    <span wire:loading.remove wire:target="parseFile">Read File</span>
                    <span wire:loading wire:target="parseFile">Reading…</span>
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if (! empty($extractedQuestions))
        <x-filament::section>
            <x-slot name="heading">2. Review &amp; confirm</x-slot>
            <x-slot name="description">Edit any field if needed, uncheck rows you don't want to import, then confirm.</x-slot>

            <div class="space-y-4">
                @foreach ($extractedQuestions as $index => $question)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="checkbox" wire:model="extractedQuestions.{{ $index }}.included" class="rounded">
                                Include question #{{ $index + 1 }}
                            </label>

                            <button type="button" wire:click="removeExtracted({{ $index }})" class="text-xs text-danger-600 hover:underline">
                                Remove
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium text-gray-500">Question (Arabic)</label>
                                <textarea wire:model="extractedQuestions.{{ $index }}.question_text_ar" rows="2" dir="rtl"
                                    class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"></textarea>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium text-gray-500">Question (English, optional)</label>
                                <textarea wire:model="extractedQuestions.{{ $index }}.question_text_en" rows="2"
                                    class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"></textarea>
                            </div>

                            @foreach (['a', 'b', 'c', 'd'] as $letter)
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Option {{ strtoupper($letter) }} (Arabic)</label>
                                    <input type="text" wire:model="extractedQuestions.{{ $index }}.option_{{ $letter }}_ar" dir="rtl"
                                        class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                </div>
                            @endforeach

                            <div>
                                <label class="text-xs font-medium text-gray-500">Correct Answer</label>
                                <select wire:model="extractedQuestions.{{ $index }}.correct_answer"
                                    class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                                    <option value="a">A</option>
                                    <option value="b">B</option>
                                    <option value="c">C</option>
                                    <option value="d">D</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-medium text-gray-500">Difficulty</label>
                                <select wire:model="extractedQuestions.{{ $index }}.difficulty_level"
                                    class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium text-gray-500">Explanation (Arabic)</label>
                                <textarea wire:model="extractedQuestions.{{ $index }}.explanation_ar" rows="2" dir="rtl"
                                    class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"></textarea>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <x-filament::button color="success" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport">
                    Confirm Import
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
