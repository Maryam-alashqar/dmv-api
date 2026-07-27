<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Notification as AppNotification;
use App\Models\Question;
use App\Models\State;
use App\Models\User;
use App\Services\AiQuestionExtractor;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\WithFileUploads;

class AiImport extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'AI Import';

    protected static ?int $navigationSort = 996;

    protected string $view = 'filament.pages.ai-import';

    public ?array $data = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $uploadedFile = null;

    /** @var array<int, array<string, mixed>> */
    public array $extractedQuestions = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('state_id')
                    ->label('State')
                    ->options(State::query()->orderBy('name_en')->pluck('name_en', 'id'))
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),

                Select::make('category_id')
                    ->label('Category')
                    ->options(fn (Get $get) => $get('state_id')
                        ? Category::query()
                            ->where('state_id', $get('state_id'))
                            ->orderBy('sequence_order')
                            ->pluck('name_en', 'id')
                        : [])
                    ->searchable()
                    ->live()
                    ->required()
                    ->disabled(fn (Get $get) => ! $get('state_id')),
            ])
            ->statePath('data');
    }

    public function extractQuestions(): void
    {
        $this->form->getState();

        if (! $this->uploadedFile) {
            Notification::make()->title('Choose a PDF, Word, or image file first.')->danger()->send();

            return;
        }

        try {
            $questions = app(AiQuestionExtractor::class)->extract($this->uploadedFile);
        } catch (\Throwable $e) {
            report($e);

            Notification::make()->title('AI extraction failed: ' . $e->getMessage())->danger()->send();

            return;
        }

        $this->extractedQuestions = collect($questions)
            ->map(fn (array $question) => array_merge(
                array_fill_keys([
                    'question_text_ar', 'question_text_en',
                    'option_a_ar', 'option_b_ar', 'option_c_ar', 'option_d_ar',
                    'option_a_en', 'option_b_en', 'option_c_en', 'option_d_en',
                    'correct_answer', 'explanation_ar', 'confidence',
                ], null),
                $question,
                ['included' => true],
            ))
            ->all();

        if (empty($this->extractedQuestions)) {
            Notification::make()->title('No questions could be extracted from this file.')->warning()->send();
        } else {
            Notification::make()->title(count($this->extractedQuestions) . ' question(s) extracted — review below before confirming.')->success()->send();
        }
    }

    public function confirmImport(): void
    {
        $data = $this->form->getState();

        $included = collect($this->extractedQuestions)->filter(fn (array $q) => $q['included'] ?? false);

        if ($included->isEmpty()) {
            Notification::make()->title('No questions selected to import.')->warning()->send();

            return;
        }

        foreach ($included as $question) {
            Question::create([
                'state_id' => $data['state_id'],
                'category_id' => $data['category_id'],
                'question_text_ar' => $question['question_text_ar'],
                'question_text_en' => $question['question_text_en'] ?: null,
                'question_type' => 'text',
                'option_a_ar' => $question['option_a_ar'],
                'option_b_ar' => $question['option_b_ar'],
                'option_c_ar' => $question['option_c_ar'],
                'option_d_ar' => $question['option_d_ar'],
                'option_a_en' => $question['option_a_en'] ?: null,
                'option_b_en' => $question['option_b_en'] ?: null,
                'option_c_en' => $question['option_c_en'] ?: null,
                'option_d_en' => $question['option_d_en'] ?: null,
                'correct_answer' => $question['correct_answer'],
                'explanation_ar' => $question['explanation_ar'],
                'difficulty_level' => 'medium',
                'source_type' => 'ai',
                'import_status' => 'active',
                'is_active' => true,
            ]);
        }

        $this->notifyStateUsersOfNewContent((int) $data['state_id'], $included->count());

        Notification::make()->title($included->count() . ' question(s) imported successfully.')->success()->send();

        $this->extractedQuestions = [];
        $this->uploadedFile = null;
    }

    /**
     * FR-44: notify users who have this state selected that new content is
     * available. Fired once per confirmed import batch (not per question)
     * to avoid spamming users when an admin reviews/confirms many at once.
     */
    private function notifyStateUsersOfNewContent(int $stateId, int $questionCount): void
    {
        $state = State::find($stateId);

        if (! $state) {
            return;
        }

        $message = "تمت إضافة {$questionCount} سؤال جديد لولاية {$state->name_ar}. جرّبها الآن!";

        User::query()
            ->where('selected_state_id', $stateId)
            ->each(function (User $user) use ($message) {
                AppNotification::create([
                    'user_id' => $user->id,
                    'title_ar' => 'محتوى جديد متاح',
                    'message_ar' => $message,
                    'type' => 'content_update',
                    'read_status' => false,
                ]);
            });
    }

    public function removeExtracted(int $index): void
    {
        unset($this->extractedQuestions[$index]);
    }
}
