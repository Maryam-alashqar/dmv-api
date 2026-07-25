<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 999;

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'free_questions_limit' => Setting::get(
                'free_questions_limit',
                config('dmv.free_questions_limit', 10)
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('free_questions_limit')
                    ->label('Free Questions Quota')
                    ->helperText('Number of free practice questions each account gets before a subscription is required (BR-01). Takes effect immediately across the app, no deployment needed.')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('free_questions_limit', $data['free_questions_limit'], 'integer');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
