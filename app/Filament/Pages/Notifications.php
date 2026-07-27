<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use App\Models\State;
use App\Models\User;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class Notifications extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedBell;

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?int $navigationSort = 999;

    protected static ?string $title = 'Send Notification';

    protected string $view = 'filament.pages.notifications';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

public function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Select::make('recipient_type')
                ->label('Send To')
                ->options([
                    'all' => 'All Users',
                    'user' => 'Specific User',
                    'state' => 'Users by State',
                ])
                ->live()
                ->required(),

            Select::make('user_id')
                ->label('User')
                ->options(
                    User::query()
                        ->orderBy('full_name')
                        ->pluck('full_name', 'id')
                )
                ->searchable()
                ->visible(fn (Get $get): bool =>
                    $get('recipient_type') === 'user'
                )
                ->required(fn (Get $get): bool =>
                    $get('recipient_type') === 'user'
                ),

            Select::make('state_id')
                ->label('State')
                ->options(
                    State::query()
                        ->orderBy('name_en')
                        ->pluck('name_en', 'id')
                )
                ->searchable()
                ->visible(fn (Get $get): bool =>
                    $get('recipient_type') === 'state'
                )
                ->required(fn (Get $get): bool =>
                    $get('recipient_type') === 'state'
                ),

            TextInput::make('title')
                ->label('Notification Title')
                ->required()
                ->maxLength(255),

            Textarea::make('message')
                ->label('Message')
                ->required()
                ->rows(5),
        ])
        ->statePath('data');
}
public function send(): void
{
    $data = $this->form->getState();

    $users = match ($data['recipient_type']) {
        'user' => User::where('id', $data['user_id'])->get(),

        'state' => User::where('selected_state_id', $data['state_id'])->get(),

        default => User::all(),
    };

    foreach ($users as $user) {
        Notification::create([
            'user_id' => $user->id,
            'title_ar' => $data['title'],
            'message_ar' => $data['message'],
            'type' => 'announcement',
            'read_status' => false,
        ]);
    }

    FilamentNotification::make()
        ->title('Notification sent successfully')
        ->success()
        ->send();

    $this->form->fill();
}
}
