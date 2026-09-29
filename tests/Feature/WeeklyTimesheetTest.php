<?php

use App\Jobs\SummarizeSquashedCommits;
use App\Mail\PendingCommitsReminderMail;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function readyUserForTimesheet(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

function useSystemTimezone(string $timezone): void
{
    config(['app.timezone' => $timezone]);
    date_default_timezone_set($timezone);
}

afterEach(function (): void {
    useSystemTimezone('UTC');
});

function pendingCommit(Project $project, string $committedAt, array $overrides = []): CommitTimeEntry
{
    return CommitTimeEntry::factory()->create(array_merge([
        'project_id' => $project->id,
        'committed_at' => Carbon::parse($committedAt),
        'status' => 'pending',
    ], $overrides));
}

test('a commit at sunday 23:30 sgt lands in that week, not the next, and is stored in utc', function () {
    useSystemTimezone('Asia/Singapore');
    $user = readyUserForTimesheet();
    $project = Project::factory()->create();

    // Exactly what a GitHub push payload carries.
    $sundayNight = pendingCommit($project, '2026-09-27T23:30:00+08:00', ['message' => 'sunday-night-commit']);
    $mondayMorning = pendingCommit($project, '2026-09-28T00:15:00+08:00', ['message' => 'monday-morning-commit']);

    expect(DB::table('commit_time_entries')->where('id', $sundayNight->id)->value('committed_at'))->toBe('2026-09-27 15:30:00')
        ->and($sundayNight->fresh()->committed_at->format('Y-m-d H:i'))->toBe('2026-09-27 23:30');

    $this->actingAs($user)->get(route('timesheet.index', ['week' => '2026-09-24']))
        ->assertOk()
        ->assertSee('sunday-night-commit')
        ->assertDontSee('monday-morning-commit')
        ->assertSee('Sunday, 27 Sep 2026');

    $this->actingAs($user)->get(route('timesheet.index', ['week' => '2026-09-28']))
        ->assertOk()
        ->assertSee('monday-morning-commit')
        ->assertDontSee('sunday-night-commit');
});

test('only pending commits appear: not converted, squashed or dismissed', function () {
    $user = readyUserForTimesheet();
    $project = Project::factory()->create();
    $week = now()->startOfWeek()->addDay();

    pendingCommit($project, $week->toDateTimeString(), ['message' => 'still-pending']);
    pendingCommit($project, $week->toDateTimeString(), ['message' => 'already-approved', 'status' => 'approved']);
    pendingCommit($project, $week->toDateTimeString(), ['message' => 'already-squashed', 'status' => 'squashed']);
    pendingCommit($project, $week->toDateTimeString(), ['message' => 'already-dismissed', 'status' => 'ignored']);

    $response = $this->actingAs($user)->get(route('timesheet.index'))->assertOk();

    $response->assertSee('still-pending')
        ->assertDontSee('already-approved')
        ->assertDontSee('already-squashed');

    // Dismissed commits are only listed in the collapsed "dismissed" section for restoring.
    expect(substr_count($response->getContent(), 'already-dismissed'))->toBe(1);
});

test('the week view groups by day and project with a suggested stage, summary and 15 minutes', function () {
    $user = readyUserForTimesheet();
    $project = Project::factory()->create(['code' => 'PRJ-WEEK']);
    $stage = ProjectStage::query()->create(['name' => 'Testing', 'sort_order' => 5, 'status' => 'active', 'keywords' => ['test']]);

    pendingCommit($project, now()->startOfWeek()->addHours(10)->toDateTimeString(), [
        'message' => "test: cover checkout\n\nlong body",
        'ai_summary' => null,
    ]);

    $this->actingAs($user)->get(route('timesheet.index'))
        ->assertOk()
        ->assertSee('PRJ-WEEK')
        ->assertSee('value="'.$stage->uuid.'" selected', false)
        ->assertSee('value="test: cover checkout"', false)
        ->assertSee('value="15"', false);
});

test('bulk convert creates one entry per commit, durations snapped to 15 minutes', function () {
    $user = readyUserForTimesheet();
    $project = Project::factory()->create(['is_billable' => true]);
    $day = now()->startOfWeek()->addHours(9);
    $a = pendingCommit($project, $day->toDateTimeString());
    $b = pendingCommit($project, $day->copy()->addHour()->toDateTimeString());

    $this->actingAs($user)->post(route('timesheet.convert'), [
        'selected' => [$a->uuid, $b->uuid],
        'rows' => [
            $a->uuid => ['minutes' => 20, 'notes' => 'Built checkout'],
            $b->uuid => ['minutes' => 50],
        ],
    ])->assertRedirect(route('timesheet.index'));

    $entries = TimeEntry::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('duration_minutes')->all())->toBe([15, 45])
        ->and($entries->every(fn ($e) => $e->duration_minutes % 15 === 0))->toBeTrue()
        ->and($entries[0]->notes)->toBe('Built checkout')
        ->and($entries[0]->entry_date->toDateString())->toBe($day->toDateString())
        ->and($a->fresh()->status)->toBe('approved')
        ->and($a->fresh()->converted_time_entry_id)->toBe($entries[0]->id);
});

test('bulk convert can create one entry per day and project group', function () {
    $user = readyUserForTimesheet();
    $project = Project::factory()->create();
    $other = Project::factory()->create();
    $monday = now()->startOfWeek()->addHours(9);

    $commits = [
        pendingCommit($project, $monday->toDateTimeString(), ['ai_summary' => 'One']),
        pendingCommit($project, $monday->copy()->addHour()->toDateTimeString(), ['ai_summary' => 'Two']),
        pendingCommit($project, $monday->copy()->addDay()->toDateTimeString(), ['ai_summary' => 'Three']),
        pendingCommit($other, $monday->toDateTimeString(), ['ai_summary' => 'Four']),
    ];

    $this->actingAs($user)->post(route('timesheet.convert'), [
        'selected' => array_map(fn ($c) => $c->uuid, $commits),
        'group' => 1,
    ])->assertRedirect(route('timesheet.index'));

    $entries = TimeEntry::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(3)
        ->and($entries[0]->duration_minutes)->toBe(30)
        ->and($entries[0]->notes)->toBe('One; Two')
        ->and($commits[1]->fresh()->converted_time_entry_id)->toBe($entries[0]->id);
});

test('one failure rolls back the whole convert batch', function () {
    $user = readyUserForTimesheet();
    $project = Project::factory()->create();
    $day = now()->startOfWeek()->addHours(9);
    $good = pendingCommit($project, $day->toDateTimeString(), ['ai_summary' => 'good']);
    $bad = pendingCommit($project, $day->copy()->addHour()->toDateTimeString(), ['ai_summary' => 'explode']);

    TimeEntry::creating(function (TimeEntry $entry): void {
        if ($entry->notes === 'explode') {
            throw new RuntimeException('Simulated failure');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($user)->post(route('timesheet.convert'), ['selected' => [$good->uuid, $bad->uuid]]))
        ->toThrow(RuntimeException::class);

    expect(TimeEntry::query()->count())->toBe(0)
        ->and($good->fresh()->status)->toBe('pending')
        ->and($bad->fresh()->status)->toBe('pending');
});

test('a double-clicked convert never creates duplicate entries', function () {
    $user = readyUserForTimesheet();
    $project = Project::factory()->create();
    $commit = pendingCommit($project, now()->startOfWeek()->addHours(9)->toDateTimeString());

    $payload = ['selected' => [$commit->uuid]];

    $this->actingAs($user)->post(route('timesheet.convert'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('timesheet.convert'), $payload)->assertSessionHasNoErrors();

    expect(TimeEntry::query()->count())->toBe(1);
});

test('dismissed commits are hidden and can be restored', function () {
    $user = readyUserForTimesheet();
    $project = Project::factory()->create();
    $commit = pendingCommit($project, now()->startOfWeek()->addHours(9)->toDateTimeString(), ['message' => 'noise-commit']);

    $this->actingAs($user)->post(route('timesheet.dismiss'), ['selected' => [$commit->uuid]])->assertRedirect(route('timesheet.index'));

    expect($commit->fresh()->status)->toBe('ignored');
    $this->actingAs($user)->get(route('timesheet.index'))->assertSee('Dismissed this week (1)');

    $this->actingAs($user)->post(route('timesheet.restore'), ['selected' => [$commit->uuid]])->assertRedirect(route('timesheet.index'));

    expect($commit->fresh()->status)->toBe('pending');
});

test('squashing from the week view queues SummarizeSquashedCommits like the project view', function () {
    Queue::fake();
    $user = readyUserForTimesheet();
    $project = Project::factory()->create();
    $day = now()->startOfWeek()->addHours(9);
    $anchor = pendingCommit($project, $day->toDateTimeString());
    $child = pendingCommit($project, $day->copy()->addMinutes(20)->toDateTimeString());

    $this->actingAs($user)->post(route('timesheet.squash'), ['selected' => [$child->uuid, $anchor->uuid]])
        ->assertRedirect(route('timesheet.index'));

    expect($child->fresh()->status)->toBe('squashed')
        ->and($child->fresh()->squashed_into)->toBe($anchor->id);

    Queue::assertPushed(SummarizeSquashedCommits::class, fn ($job) => $job->anchor->id === $anchor->id);
});

test('squash rejects commits from different projects', function () {
    Queue::fake();
    $user = readyUserForTimesheet();
    $day = now()->startOfWeek()->addHours(9)->toDateTimeString();
    $a = pendingCommit(Project::factory()->create(), $day);
    $b = pendingCommit(Project::factory()->create(), $day);

    $this->actingAs($user)->post(route('timesheet.squash'), ['selected' => [$a->uuid, $b->uuid]])->assertSessionHasErrors('selected');

    Queue::assertNothingPushed();
});

test('the week total is shown against the weekly target', function () {
    $user = readyUserForTimesheet();
    SystemSetting::upsertValues(['weekly_hours_target' => '30']);
    TimeEntry::factory()->create(['entry_date' => now()->startOfWeek()->toDateString(), 'duration_minutes' => 90]);
    TimeEntry::factory()->create(['entry_date' => now()->startOfWeek()->subDay()->toDateString(), 'duration_minutes' => 600]);

    $this->actingAs($user)->get(route('timesheet.index'))
        ->assertOk()
        ->assertSee('1.50 h')
        ->assertSee('/ 30.00 h');
});

test('the reminder only sends when commits are pending', function () {
    Mail::fake();
    $user = readyUserForTimesheet();

    $this->artisan('desk:timesheet-reminder')->expectsOutputToContain('No pending commits')->assertSuccessful();
    Mail::assertNothingSent();

    pendingCommit(Project::factory()->create(), now()->toDateTimeString());
    pendingCommit(Project::factory()->create(), now()->toDateTimeString());

    $this->artisan('desk:timesheet-reminder')->assertSuccessful();

    Mail::assertSent(PendingCommitsReminderMail::class, fn ($mail) => $mail->hasTo($user->email) && $mail->pendingCount === 2);
});

test('the reminder is scheduled weekly', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(fn ($event) => str_contains((string) $event->command, 'desk:timesheet-reminder')))->toBeTrue();
});

test('bot timesheet endpoint requires a sanctum token and returns the pending count', function () {
    $project = Project::factory()->create(['code' => 'PRJ-BOT']);
    pendingCommit($project, now()->toDateTimeString());
    pendingCommit($project, now()->subWeeks(2)->toDateTimeString());
    pendingCommit($project, now()->toDateTimeString(), ['status' => 'approved']);

    $this->getJson('/api/bot/timesheet/pending')->assertUnauthorized();

    $token = User::factory()->create()->createToken('bot')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/bot/timesheet/pending')
        ->assertOk()
        ->assertJsonPath('pending_count', 2)
        ->assertJsonPath('this_week_count', 1)
        ->assertJsonPath('projects.0.code', 'PRJ-BOT')
        ->assertJsonPath('projects.0.pending_count', 2);
});

test('in archive mode the page is viewable and every action is blocked', function () {
    Queue::fake();
    $user = readyUserForTimesheet();
    $project = Project::factory()->create();
    $day = now()->startOfWeek()->addHours(9)->toDateTimeString();
    $a = pendingCommit($project, $day, ['message' => 'archived-view-commit']);
    $b = pendingCommit($project, $day);
    SystemSetting::upsertValues(['archive_mode' => true]);

    $this->actingAs($user)->get(route('timesheet.index'))->assertOk()->assertSee('archived-view-commit');

    foreach (['timesheet.convert', 'timesheet.squash', 'timesheet.dismiss', 'timesheet.restore'] as $routeName) {
        $this->actingAs($user)->post(route($routeName), ['selected' => [$a->uuid, $b->uuid]])
            ->assertSessionHas('status', 'archive-mode-read-only');
    }

    expect(TimeEntry::query()->count())->toBe(0)
        ->and($a->fresh()->status)->toBe('pending');
    Queue::assertNothingPushed();
});

test('the utc data migration rewrites existing wall-clock commit times from the system timezone', function () {
    SystemSetting::upsertValues(['timezone' => 'Asia/Singapore']);
    $commit = CommitTimeEntry::factory()->create();
    DB::table('commit_time_entries')->where('id', $commit->id)->update(['committed_at' => '2026-09-27 23:30:00']);

    $migration = require database_path('migrations/2026_09_29_050001_convert_commit_timestamps_to_utc.php');

    $migration->up();
    expect(DB::table('commit_time_entries')->where('id', $commit->id)->value('committed_at'))->toBe('2026-09-27 15:30:00');

    $migration->down();
    expect(DB::table('commit_time_entries')->where('id', $commit->id)->value('committed_at'))->toBe('2026-09-27 23:30:00');
});
