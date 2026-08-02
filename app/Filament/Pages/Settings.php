<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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
            'about_title_ar' => Setting::get('about_title_ar', 'من نحن'),
            'about_title_en' => Setting::get('about_title_en', 'About Us'),
            'about_content_ar' => Setting::get('about_content_ar', ''),
            'about_content_en' => Setting::get('about_content_en', ''),
            'privacy_title_ar' => Setting::get('privacy_title_ar', 'سياسة الخصوصية'),
            'privacy_title_en' => Setting::get('privacy_title_en', 'Privacy Policy'),
            'privacy_content_ar' => Setting::get('privacy_content_ar', ''),
            'privacy_content_en' => Setting::get('privacy_content_en', ''),
            'terms_title_ar' => Setting::get('terms_title_ar', 'الشروط والأحكام'),
            'terms_title_en' => Setting::get('terms_title_en', 'Terms of Use'),
            'terms_content_ar' => Setting::get('terms_content_ar', ''),
            'terms_content_en' => Setting::get('terms_content_en', ''),
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
                Section::make('About Us')->schema($this->contentFields('about')),
                Section::make('Privacy Policy')->schema($this->contentFields('privacy')),
                Section::make('Terms and Conditions')->schema($this->contentFields('terms')),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('free_questions_limit', $data['free_questions_limit'], 'integer');

        foreach (['about', 'privacy', 'terms'] as $page) {
            foreach (['title_ar', 'title_en', 'content_ar', 'content_en'] as $field) {
                Setting::set("{$page}_{$field}", $data["{$page}_{$field}"] ?? '');
            }
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    private function contentFields(string $page): array
    {
        return [
            TextInput::make("{$page}_title_ar")->label('Arabic title')->required()->maxLength(255),
            TextInput::make("{$page}_title_en")->label('English title')->required()->maxLength(255),
            Textarea::make("{$page}_content_ar")->label('Arabic content')->rows(10)->required()->columnSpanFull(),
            Textarea::make("{$page}_content_en")->label('English content')->rows(10)->required()->columnSpanFull(),
        ];
    }
}
