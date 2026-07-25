<?php

namespace App\Filament\Pages;

use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\State;
use App\Models\UserAnswer;
use App\Models\UserSubscription;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Reports extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 997;

    protected string $view = 'filament.pages.reports';

    public ?array $data = [
        'period' => 'month',
        'state_id' => null,
    ];

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('period')
                    ->label('Period')
                    ->options([
                        'today' => 'Today',
                        'week' => 'This Week',
                        'month' => 'This Month',
                        'year' => 'This Year',
                        'all' => 'All Time',
                    ])
                    ->default('month')
                    ->live()
                    ->required(),

                Select::make('state_id')
                    ->label('State')
                    ->options(State::query()->orderBy('name_en')->pluck('name_en', 'id'))
                    ->searchable()
                    ->live()
                    ->placeholder('All States (question performance & usage-by-state only)'),
            ])
            ->statePath('data');
    }

    private function periodStart(): ?Carbon
    {
        return match ($this->data['period'] ?? 'month') {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => null,
        };
    }

    private function selectedStateId(): ?int
    {
        return $this->data['state_id'] ?? null;
    }

    /**
     * FR-A20: MRR, subscriptions sold, and revenue for the selected period.
     */
    public function getRevenueStats(): array
    {
        $start = $this->periodStart();

        $paymentsQuery = Payment::query()->where('payment_status', 'confirmed');

        if ($start) {
            $paymentsQuery->where('created_at', '>=', $start);
        }

        $revenueInPeriod = (float) (clone $paymentsQuery)->sum('amount_usd');
        $subscriptionsSold = (clone $paymentsQuery)->count();

        $mrr = (float) UserSubscription::query()
            ->join('subscription_packages', 'subscription_packages.id', '=', 'user_subscriptions.package_id')
            ->where('user_subscriptions.status', 'active')
            ->where('user_subscriptions.expiry_date', '>', now())
            ->selectRaw('COALESCE(SUM(subscription_packages.price_usd / GREATEST(subscription_packages.duration_days, 1) * 30), 0) as mrr')
            ->value('mrr');

        return [
            'revenue' => round($revenueInPeriod, 2),
            'mrr' => round($mrr, 2),
            'subscriptions_sold' => $subscriptionsSold,
        ];
    }

    /**
     * Revenue grouped by the payer's currently selected state.
     * Caveat: Payment doesn't record the state at time of purchase, so this
     * reflects each user's CURRENT selected state, not necessarily the one
     * active when they paid.
     */
    public function getRevenueByState(): Collection
    {
        $start = $this->periodStart();

        $query = Payment::query()
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->join('states', 'states.id', '=', 'users.selected_state_id')
            ->where('payments.payment_status', 'confirmed');

        if ($start) {
            $query->where('payments.created_at', '>=', $start);
        }

        return $query
            ->selectRaw('states.id as state_id, states.name_en, states.name_ar, COUNT(*) as payments_count, SUM(payments.amount_usd) as revenue')
            ->groupBy('states.id', 'states.name_en', 'states.name_ar')
            ->orderByDesc('revenue')
            ->get();
    }

    /**
     * FR-A19: active users, attempts, and completion rate for the selected period/state.
     */
    public function getUsageStats(): array
    {
        $start = $this->periodStart();
        $stateId = $this->selectedStateId();

        $attemptsQuery = ExamAttempt::query();

        if ($start) {
            $attemptsQuery->where('created_at', '>=', $start);
        }

        if ($stateId) {
            $attemptsQuery->where('state_id', $stateId);
        }

        $totalAttempts = (clone $attemptsQuery)->count();
        $completedAttempts = (clone $attemptsQuery)->where('completion_status', 'completed')->count();
        $activeUsers = (clone $attemptsQuery)->distinct('user_id')->count('user_id');

        return [
            'active_users' => $activeUsers,
            'total_attempts' => $totalAttempts,
            'completion_rate' => $totalAttempts > 0 ? round($completedAttempts / $totalAttempts * 100, 1) : 0,
        ];
    }

    public function getActiveUsersByState(): Collection
    {
        $start = $this->periodStart();

        $query = ExamAttempt::query()
            ->join('states', 'states.id', '=', 'exam_attempts.state_id');

        if ($start) {
            $query->where('exam_attempts.created_at', '>=', $start);
        }

        return $query
            ->selectRaw('states.id as state_id, states.name_en, states.name_ar, COUNT(DISTINCT exam_attempts.user_id) as active_users, COUNT(*) as attempts')
            ->groupBy('states.id', 'states.name_en', 'states.name_ar')
            ->orderByDesc('active_users')
            ->get();
    }

    /**
     * FR-A21: lowest correct-answer-rate questions (min. 3 answers, to avoid
     * a single lucky/unlucky guess skewing the ranking).
     */
    public function getWorstQuestions(): Collection
    {
        $query = UserAnswer::query()
            ->join('questions', 'questions.id', '=', 'user_answers.question_id')
            ->join('states', 'states.id', '=', 'questions.state_id')
            ->join('categories', 'categories.id', '=', 'questions.category_id');

        if ($stateId = $this->selectedStateId()) {
            $query->where('questions.state_id', $stateId);
        }

        return $query
            ->selectRaw('
                questions.id as question_id,
                questions.question_text_ar,
                states.name_en as state_name,
                categories.name_en as category_name,
                COUNT(*) as total_answers,
                SUM(CASE WHEN user_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
            ')
            ->groupBy('questions.id', 'questions.question_text_ar', 'states.name_en', 'categories.name_en')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByRaw('SUM(CASE WHEN user_answers.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(*) ASC')
            ->limit(20)
            ->get();
    }
}
