<?php

use App\Contracts\CommitSummarizer;
use App\Jobs\SummarizeCommit;
use App\Models\AiRequest;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Ai\AiSettings;
use App\Services\Ai\CommitSummaryGateway;
use App\Services\Ai\Redactor;
use App\Services\AnthropicCommitSummarizer;
use App\Services\NullCommitSummarizer;
use App\Services\OpenAiCommitSummarizer;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

function readyUserForAi(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $extra
 */
function configureAi(string $provider, array $extra = []): void
{
    SystemSetting::upsertValues(array_merge([
        'ai_enabled' => true,
        'ai_provider' => $provider,
        'ai_api_key' => Crypt::encryptString('secret-provider-key'),
    ], $extra));
}

function aiStages(): void
{
    ProjectStage::query()->create(['name' => 'Development', 'sort_order' => 1, 'status' => 'active', 'keywords' => ['refactor']]);
    ProjectStage::query()->create(['name' => 'Testing', 'sort_order' => 2, 'status' => 'active', 'keywords' => ['spec']]);
}

function aiCommit(array $overrides = []): CommitTimeEntry
{
    return CommitTimeEntry::factory()->create(array_merge([
        'project_id' => Project::factory()->create()->id,
        'message' => 'Build checkout page',
        'branch' => 'feature/checkout',
        'author_name' => 'Alice Example',
        'author_email' => 'alice.author@example.com',
        'changed_files' => 3,
        'file_paths' => ['app/Http/Controllers/CheckoutController.php'],
        'status' => 'pending',
    ], $overrides));
}

function openAiReply(string $summary = 'Built the checkout page.', string $stage = 'Development'): array
{
    return ['choices' => [['message' => ['content' => json_encode(['summary' => $summary, 'stage' => $stage])]]]];
}

function runSummaryJob(CommitTimeEntry $commit): void
{
    (new SummarizeCommit($commit))->handle(app(CommitSummaryGateway::class));
}

test('on a fresh install the null summarizer is bound and no http request is made', function () {
    Http::fake();
    config(['services.openai.api_key' => 'sk-from-env-should-not-be-used']);
    aiStages();
    $commit = aiCommit();

    expect(app(CommitSummarizer::class))->toBeInstanceOf(NullCommitSummarizer::class)
        ->and(app(AiSettings::class)->enabled())->toBeFalse();

    runSummaryJob($commit);

    Http::assertNothingSent();
    expect(AiRequest::query()->count())->toBe(0)
        ->and($commit->fresh()->ai_summary)->toBeNull();
});

test('each driver calls its own endpoint with the configured model', function (string $provider, array $extra, string $url, string $model, array $reply, string $driver) {
    Http::fake(['*' => Http::response($reply)]);
    configureAi($provider, $extra);
    aiStages();
    $commit = aiCommit();

    expect(app(CommitSummarizer::class))->toBeInstanceOf($driver);

    runSummaryJob($commit);

    Http::assertSent(fn (Request $request) => $request->url() === $url && $request['model'] === $model);
    expect($commit->fresh()->ai_summary)->toBe('Built the checkout page.');
})->with([
    'openai' => [
        'openai', ['ai_model' => 'gpt-4o-mini'],
        'https://api.openai.com/v1/chat/completions', 'gpt-4o-mini',
        openAiReply(), OpenAiCommitSummarizer::class,
    ],
    'anthropic' => [
        'anthropic', ['ai_model' => 'claude-opus-5'],
        'https://api.anthropic.com/v1/messages', 'claude-opus-5',
        ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => '{"summary":"Built the checkout page.","stage":"Development"}']]],
        AnthropicCommitSummarizer::class,
    ],
    'openai-compatible local endpoint' => [
        'openai_compatible', ['ai_model' => 'llama3.1', 'ai_base_url' => 'http://localhost:11434/v1', 'ai_api_key' => null],
        'http://localhost:11434/v1/chat/completions', 'llama3.1',
        openAiReply(), OpenAiCommitSummarizer::class,
    ],
]);

test('the anthropic driver sends the required headers and treats a refusal as no summary', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['stop_reason' => 'refusal', 'content' => []])]);
    configureAi('anthropic', ['ai_model' => 'claude-opus-5']);
    aiStages();
    $commit = aiCommit();

    runSummaryJob($commit);

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'secret-provider-key')
        && $request->hasHeader('anthropic-version', '2023-06-01')
        && $request['fallbacks'] === 'default');
    expect($commit->fresh()->ai_summary)->toBeNull();
});

test('the request body holds only allow-listed fields and never the author email, name or diff', function () {
    Http::fake(['*' => Http::response(openAiReply())]);
    configureAi('openai');
    aiStages();
    runSummaryJob(aiCommit());

    Http::assertSent(function (Request $request): bool {
        $prompt = $request['messages'][0]['content'];
        $payload = json_decode(substr($prompt, strpos($prompt, '{'), strrpos($prompt, "}\n\nRespond") - strpos($prompt, '{') + 1), true);

        return array_keys($request->data()) === ['model', 'messages', 'max_tokens', 'response_format']
            && array_keys($payload) === ['commits', 'stages']
            && array_keys($payload['commits'][0]) === ['message', 'branch', 'changed_files']
            && array_keys($payload['stages'][0]) === ['name', 'keywords']
            && ! str_contains($request->body(), 'alice.author@example.com')
            && ! str_contains($request->body(), 'Alice Example')
            && ! str_contains($request->body(), 'CheckoutController.php')
            && ! str_contains($request->body(), 'diff');
    });
});

test('file paths are only sent when switched on', function () {
    Http::fake(['*' => Http::response(openAiReply())]);
    configureAi('openai', ['ai_send_file_paths' => true]);
    aiStages();
    runSummaryJob(aiCommit());

    Http::assertSent(fn (Request $request) => str_contains($request->body(), 'CheckoutController.php'));
});

test('token-like strings and emails in commit messages are masked', function () {
    Http::fake(['*' => Http::response(openAiReply())]);
    configureAi('openai');
    aiStages();

    runSummaryJob(aiCommit([
        'message' => 'Rotate key sk-proj-AbC123dEf456GhI789 and ghp_1234567890abcdefghijABCD for bob@client.example; password=hunter2',
    ]));

    Http::assertSent(function (Request $request): bool {
        $body = $request->body();

        return str_contains($body, '[redacted]')
            && ! str_contains($body, 'sk-proj-AbC123dEf456GhI789')
            && ! str_contains($body, 'ghp_1234567890abcdefghijABCD')
            && ! str_contains($body, 'bob@client.example')
            && ! str_contains($body, 'hunter2')
            && str_contains($body, 'Rotate key');
    });
});

test('the redactor masks common secret shapes but keeps ordinary words', function () {
    $redactor = new Redactor;

    expect($redactor->redact('Bearer eyJhbGciOi.eyJzdWIiOi.SflKxwRJSM'))->toBe('[redacted]')
        ->and($redactor->redact('AKIAIOSFODNN7EXAMPLE'))->toBe('[redacted]')
        ->and($redactor->redact('api_key: abc'))->toBe('[redacted]')
        ->and($redactor->redact('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8'))->toBe('[redacted]')
        ->and($redactor->redact('Refactor the invoice totals calculation'))->toBe('Refactor the invoice totals calculation');
});

test('a project that opted out is never summarised, even with ai on', function () {
    Http::fake();
    configureAi('openai');
    aiStages();
    $commit = aiCommit(['project_id' => Project::factory()->create(['ai_opt_out' => true])->id]);

    runSummaryJob($commit);

    Http::assertNothingSent();
    expect(AiRequest::query()->count())->toBe(0);
});

test('projects can opt out from the project form', function () {
    $user = readyUserForAi();
    $project = Project::factory()->create();

    $this->actingAs($user)->put(route('projects.update', $project), [
        'code' => $project->code,
        'name' => $project->name,
        'status' => 'active',
        'is_billable' => true,
        'ai_opt_out' => true,
    ])->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->ai_opt_out)->toBeTrue();
});

test('a provider timeout or 500 leaves the commit pending, retries with backoff, then fails quietly', function () {
    Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);
    configureAi('openai');
    aiStages();
    $commit = aiCommit();
    $job = new SummarizeCommit($commit);

    expect(fn () => $job->handle(app(CommitSummaryGateway::class)))->toThrow(RequestException::class);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 120])
        ->and($commit->fresh()->status)->toBe('pending')
        ->and($commit->fresh()->ai_summary)->toBeNull()
        ->and(AiRequest::query()->first()->outcome)->toBe('error');

    $job->failed(new RuntimeException('gave up'));

    expect($commit->fresh()->status)->toBe('pending');
});

test('every call writes one log row holding a hash, not the payload', function () {
    Http::fake(['*' => Http::response(openAiReply())]);
    configureAi('openai', ['ai_model' => 'gpt-4o-mini']);
    aiStages();
    $commit = aiCommit(['message' => 'Unique-message-marker for checkout']);

    runSummaryJob($commit);

    $log = AiRequest::query()->sole();

    expect($log->provider)->toBe('openai')
        ->and($log->model)->toBe('gpt-4o-mini')
        ->and($log->purpose)->toBe('commit_summary')
        ->and($log->project_id)->toBe($commit->project_id)
        ->and($log->outcome)->toBe('success')
        ->and($log->payload_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($log->payload_length)->toBeGreaterThan(0)
        ->and(json_encode($log->getAttributes()))->not->toContain('Unique-message-marker');
});

test('a stage keyword match still skips ai', function () {
    Http::fake();
    configureAi('openai');
    aiStages();

    runSummaryJob(aiCommit(['message' => 'refactor invoice totals']));

    Http::assertNothingSent();
});

test('the prune command deletes log rows past retention', function () {
    SystemSetting::upsertValues(['ai_log_retention_days' => 30]);
    $row = fn ($at) => AiRequest::query()->create([
        'requested_at' => $at, 'provider' => 'openai', 'model' => 'm', 'purpose' => 'commit_summary',
        'payload_hash' => str_repeat('a', 64), 'payload_length' => 10, 'outcome' => 'success',
    ]);
    $row(now()->subDays(45));
    $kept = $row(now()->subDays(5));

    $this->artisan('desk:prune-ai-requests')->expectsOutputToContain('Pruned 1')->assertSuccessful();

    expect(AiRequest::query()->pluck('id')->all())->toBe([$kept->id]);
});

test('api keys are stored encrypted and never rendered back into the settings form', function () {
    $user = readyUserForAi();

    $this->actingAs($user)->put(route('settings.ai.update'), [
        'ai_enabled' => true,
        'ai_provider' => 'anthropic',
        'ai_model' => 'claude-opus-5',
        'ai_api_key' => 'sk-ant-super-secret-value-123',
        'ai_send_file_paths' => false,
        'ai_log_retention_days' => 90,
    ])->assertRedirect(route('settings.ai.edit'));

    $stored = SystemSetting::query()->where('key', 'ai_api_key')->value('value');

    expect($stored)->not->toContain('sk-ant-super-secret-value-123')
        ->and(Crypt::decryptString($stored))->toBe('sk-ant-super-secret-value-123')
        ->and(app(AiSettings::class)->apiKey())->toBe('sk-ant-super-secret-value-123');

    $this->actingAs($user)->get(route('settings.ai.edit'))
        ->assertOk()
        ->assertDontSee('sk-ant-super-secret-value-123')
        ->assertDontSee($stored)
        ->assertSee('A key is saved');

    // Saving again with a blank key keeps the stored one.
    $this->actingAs($user)->put(route('settings.ai.update'), [
        'ai_enabled' => true,
        'ai_provider' => 'anthropic',
        'ai_api_key' => '',
        'ai_send_file_paths' => false,
        'ai_log_retention_days' => 90,
    ]);

    expect(app(AiSettings::class)->apiKey())->toBe('sk-ant-super-secret-value-123');
});

test('ai settings writes are blocked in archive mode', function () {
    $user = readyUserForAi();
    SystemSetting::upsertValues(['archive_mode' => true]);

    $this->actingAs($user)->put(route('settings.ai.update'), [
        'ai_enabled' => true,
        'ai_provider' => 'openai',
        'ai_send_file_paths' => false,
        'ai_log_retention_days' => 90,
    ])->assertSessionHas('status', 'archive-mode-read-only');

    expect(app(AiSettings::class)->enabled())->toBeFalse();
});

test('prelaunch check fails when ai is on but not configured', function () {
    SystemSetting::upsertValues(['ai_enabled' => true, 'ai_provider' => 'anthropic']);

    $this->artisan('desk:prelaunch-check')->expectsOutputToContain('AI provider is fully configured');

    expect(app(AiSettings::class)->isReady())->toBeFalse();
});
