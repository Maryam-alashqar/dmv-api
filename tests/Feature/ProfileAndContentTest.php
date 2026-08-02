<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    uses(RefreshDatabase::class);
} else {
    beforeEach(function () {
        $this->markTestSkipped('The pdo_sqlite PHP extension is required for database feature tests.');
    });
}

test('profile returns progress and subscription type', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['access'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/users/profile')
        ->assertOk()
        ->assertJsonPath('data.subscription_type', 'free')
        ->assertJsonPath('data.progress.completed_exams', 0)
        ->assertJsonPath('data.progress.average_score', 0)
        ->assertJsonPath('data.subscription', null);
});

test('user can upload a profile photo', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $token = $user->createToken('test', ['access'])->plainTextToken;

    $this->withToken($token)
        ->post('/api/users/profile-photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['profile_photo_url']]);

    Storage::disk('public')->assertExists($user->fresh()->getRawOriginal('profile_photo_url'));
});

test('public legal content is read from dashboard settings', function () {
    Setting::set('privacy_title_ar', 'خصوصيتك');
    Setting::set('privacy_content_ar', 'نص قابل للتعديل');

    $this->getJson('/api/privacy-policy')
        ->assertOk()
        ->assertJsonPath('data.title_ar', 'خصوصيتك')
        ->assertJsonPath('data.content_ar', 'نص قابل للتعديل');
});
