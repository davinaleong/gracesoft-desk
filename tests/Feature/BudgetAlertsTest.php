<?php

use App\Mail\BudgetAlertMail;
use App\Models\AuditLog;
use App\Models\BudgetAlert;
use App\Models\ImportBatch;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\BudgetMonitor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function readyUserForBudgets(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

function hoursBudgetProject(float $hours = 80, array $overrides = []): Project
{
    return Project::factory()->create(array_merge([
        'budget_type' => 'hours',
        'budget_value' => $hours,
        'hourly_rate' => 100,
    ], $overrides));
}

function logMinutes(Project $project, int $minutes, array $overrides = []): TimeEntry
{
    return TimeEntry::factory()->create(array_merge([
        'project_id' => $project->id,
        'duration_minutes' => $minutes,
        'is_billable' => true,
    ], $overrides));
}

beforeEach(function (): void {
    Mail::fake();
});

test('40 of 80 budgeted hours fires the 50% alert exactly once', function () {
    readyUserForBudgets();
    $project = hoursBudgetProject(80);

    logMinutes($project, 39 * 60);
    expect(BudgetAlert::query()->count())->toBe(0);

    logMinutes($project, 60);
    logMinutes($project, 30);

    $alerts = BudgetAlert::query()->get();

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->threshold)->toBe(50)
        ->and($alerts->first()->used_value)->toBe('40.00')
        ->and($alerts->first()->notified_at)->not->toBeNull();

    Mail::assertSent(BudgetAlertMail::class, 1);
});

test('one entry that crosses 50% and 80% fires both, in order', function () {
    readyUserForBudgets();
    $project = hoursBudgetProject(10);

    logMinutes($project, 9 * 60);

    expect(BudgetAlert::query()->orderBy('id')->pluck('threshold')->all())->toBe([50, 80]);
    Mail::assertSent(BudgetAlertMail::class, 2);
});

test('dropping below a threshold and crossing again does not re-alert; raising the budget re-arms', function () {
    $project = hoursBudgetProject(10);
    $entry = logMinutes($project, 6 * 60);
    expect(BudgetAlert::query()->pluck('threshold')->all())->toBe([50]);

    $entry->delete();
    logMinutes($project, 6 * 60);
    expect(BudgetAlert::query()->count())->toBe(1);

    $project->update(['budget_value' => 20]);
    expect($project->fresh()->budget_revision)->toBe(1)
        ->and(BudgetAlert::query()->count())->toBe(1);

    logMinutes($project, 5 * 60);

    expect(BudgetAlert::query()->where('budget_revision', 1)->pluck('threshold')->all())->toBe([50]);
});

test('changing only the thresholds does not re-arm already fired alerts', function () {
    $project = hoursBudgetProject(10);
    logMinutes($project, 6 * 60);

    $project->update(['budget_thresholds' => [50, 60]]);

    expect($project->fresh()->budget_revision)->toBe(0)
        ->and(BudgetAlert::query()->orderBy('threshold')->pluck('threshold')->all())->toBe([50, 60]);
});

test('non-billable hours count toward an hours budget but not a money budget', function () {
    $monitor = app(BudgetMonitor::class);
    $hours = hoursBudgetProject(10);
    $money = Project::factory()->create(['budget_type' => 'amount', 'budget_value' => 1000, 'hourly_rate' => 100]);

    logMinutes($hours, 120, ['is_billable' => false]);
    logMinutes($money, 120, ['is_billable' => false]);
    logMinutes($money, 60);

    expect($monitor->used($hours))->toBe(2.0)
        ->and($monitor->used($money))->toBe(100.0)
        ->and($monitor->percentUsed($money))->toBe(10.0);
});

test('soft-deleted entries are excluded', function () {
    $project = hoursBudgetProject(10);
    $entry = logMinutes($project, 3 * 60);
    logMinutes($project, 60);

    $entry->delete();

    expect(app(BudgetMonitor::class)->used($project))->toBe(1.0);
});

test('alerts are evaluated after a csv import commit, once per project', function () {
    Storage::fake('s3');
    $user = readyUserForBudgets();
    $project = hoursBudgetProject(4, ['code' => 'PRJ-BUD']);

    $csv = "project_code,entry_date,duration_minutes,is_billable,notes\n"
        ."PRJ-BUD,2026-09-01,60,yes,one\n"
        ."PRJ-BUD,2026-09-02,60,yes,two\n"
        ."PRJ-BUD,2026-09-03,60,yes,three\n";

    $this->actingAs($user)->post(route('time-entries.import.preview'), [
        'csv_file' => UploadedFile::fake()->createWithContent('entries.csv', $csv),
    ])->assertOk();

    $token = ImportBatch::query()->where('type', 'time_entries')->latest('id')->value('token');

    $this->actingAs($user)->post(route('time-entries.import.commit'), ['token' => $token])->assertRedirect();

    expect(TimeEntry::query()->where('project_id', $project->id)->count())->toBe(3)
        ->and(BudgetAlert::query()->where('project_id', $project->id)->pluck('threshold')->all())->toBe([50])
        ->and(BudgetAlert::query()->first()->used_value)->toBe('3.00');
});

test('the project page shows a progress bar and the dashboard lists projects at risk', function () {
    $user = readyUserForBudgets();
    $project = hoursBudgetProject(10, ['code' => 'PRJ-RISK']);
    $calm = hoursBudgetProject(100, ['code' => 'PRJ-CALM']);
    logMinutes($project, 9 * 60);
    logMinutes($calm, 60);

    $this->actingAs($user)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('aria-valuenow="90"', false)
        ->assertSeeInOrder(['9.00 / 10.00 h', '90%']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Budgets at Risk')
        ->assertSee('PRJ-RISK')
        ->assertViewHas('budgetsAtRisk', fn ($rows) => $rows->map(fn ($row) => $row['project']->code)->all() === ['PRJ-RISK']);
});

test('bot budget alerts endpoint requires a sanctum token and lists projects over threshold', function () {
    $project = hoursBudgetProject(10, ['code' => 'PRJ-BOTB']);
    hoursBudgetProject(10, ['code' => 'PRJ-QUIET']);
    logMinutes($project, 9 * 60);

    $this->getJson('/api/bot/budgets/alerts')->assertUnauthorized();

    $token = User::factory()->create()->createToken('bot')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/bot/budgets/alerts')
        ->assertOk()
        ->assertJsonCount(1, 'projects')
        ->assertJsonPath('projects.0.code', 'PRJ-BOTB')
        ->assertJsonPath('projects.0.percent', 90)
        ->assertJsonPath('alerts.0.threshold', 50)
        ->assertJsonPath('alerts.1.threshold', 80);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/bot/budgets/alerts?since='.urlencode(now()->addMinute()->toIso8601String()))
        ->assertOk()
        ->assertJsonCount(0, 'alerts');
});

test('budget changes are audit-logged and editable from the project form', function () {
    $user = readyUserForBudgets();
    $project = Project::factory()->create();

    $this->actingAs($user)->put(route('projects.update', $project), [
        'code' => $project->code,
        'name' => $project->name,
        'status' => 'active',
        'is_billable' => true,
        'budget_type' => 'hours',
        'budget_value' => '120',
        'budget_thresholds' => '75, 90',
    ])->assertRedirect(route('projects.show', $project));

    $project->refresh();

    expect($project->budget_type)->toBe('hours')
        ->and($project->budget_value)->toBe('120.00')
        ->and($project->budget_thresholds)->toBe(['75', '90'])
        ->and(app(BudgetMonitor::class)->thresholds($project))->toBe([75, 90]);

    $log = AuditLog::query()->where('auditable_type', Project::class)->where('auditable_id', $project->id)->where('action', 'updated')->latest('id')->first();

    expect($log->old_values['budget_type'])->toBe('none')
        ->and($log->new_values['budget_type'])->toBe('hours')
        ->and((float) $log->new_values['budget_value'])->toBe(120.0);
});

test('a budget type needs a positive value', function () {
    $user = readyUserForBudgets();
    $project = Project::factory()->create();

    $this->actingAs($user)->put(route('projects.update', $project), [
        'code' => $project->code,
        'name' => $project->name,
        'status' => 'active',
        'is_billable' => true,
        'budget_type' => 'amount',
        'budget_value' => '',
    ])->assertSessionHasErrors('budget_value');
});
