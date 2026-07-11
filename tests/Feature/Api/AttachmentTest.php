<?php

use App\Jobs\GenerateAttachmentThumbnail;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/*
 * Вкладення (фаза B4): аплоад на local disk, ліміти 5MB, thumbnail-job.
 */

beforeEach(function () {
    Storage::fake();
});

it('uploads an image and returns AttachmentResource shape', function () {
    Queue::fake();

    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->for($user)->create();

    actingAs($user);

    $file = UploadedFile::fake()->image('photo.jpg', 1920, 1080)->size(500);

    $response = postJson("/api/channels/{$channel->id}/messages/{$message->id}/attachments", [
        'file' => $file,
    ])->assertCreated();

    $response->assertJsonStructure([
        'id', 'url', 'thumb_url', 'mime', 'size', 'original_name', 'width', 'height',
    ]);

    expect($response->json('mime'))->toBe('image/jpeg')
        ->and($response->json('original_name'))->toBe('photo.jpg')
        ->and($response->json('width'))->toBe(1920)
        ->and($response->json('height'))->toBe(1080)
        ->and(Attachment::count())->toBe(1);

    Queue::assertPushed(GenerateAttachmentThumbnail::class);
});

it('uploads a PDF without dispatching thumbnail job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->for($user)->create();

    actingAs($user);

    $file = UploadedFile::fake()->create('document.pdf', 200, 'application/pdf');

    postJson("/api/channels/{$channel->id}/messages/{$message->id}/attachments", [
        'file' => $file,
    ])->assertCreated();

    Queue::assertNotPushed(GenerateAttachmentThumbnail::class);
});

it('rejects files exceeding the 5MB limit', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->for($user)->create();

    actingAs($user);

    $file = UploadedFile::fake()->create('huge.jpg', 6_000, 'image/jpeg');

    postJson("/api/channels/{$channel->id}/messages/{$message->id}/attachments", [
        'file' => $file,
    ])->assertUnprocessable();
});

it('rejects disallowed MIME types', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->for($user)->create();

    actingAs($user);

    $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

    postJson("/api/channels/{$channel->id}/messages/{$message->id}/attachments", [
        'file' => $file,
    ])->assertUnprocessable();
});

it('forbids non-members to upload attachments', function () {
    $owner = User::factory()->create();
    $channel = makeChannelFor($owner);
    $message = Message::factory()->for($channel)->for($owner)->create();

    $outsider = User::factory()->create();

    actingAs($outsider);

    $file = UploadedFile::fake()->image('test.jpg');

    postJson("/api/channels/{$channel->id}/messages/{$message->id}/attachments", [
        'file' => $file,
    ])->assertForbidden();
});

it('forbids uploading to a message from another channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $otherChannel = makeChannelFor($user);
    $message = Message::factory()->for($otherChannel)->for($user)->create();

    actingAs($user);

    $file = UploadedFile::fake()->image('test.jpg');

    postJson("/api/channels/{$channel->id}/messages/{$message->id}/attachments", [
        'file' => $file,
    ])->assertForbidden();
});
