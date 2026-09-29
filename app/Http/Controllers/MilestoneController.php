<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MilestoneController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        if ($project->billing_model !== Project::BILLING_FIXED_FEE) {
            throw ValidationException::withMessages(['milestone' => __('Milestones belong to fixed-fee projects.')]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'due_date' => ['nullable', 'date'],
        ]);

        $this->ensureWithinFixedFee($project, (float) $validated['amount']);

        $project->milestones()->create($validated + [
            'status' => Milestone::STATUS_PENDING,
            'sort_order' => (int) $project->milestones()->reorder()->max('sort_order') + 1,
        ]);

        return redirect()->route('projects.show', $project)->with('status', 'milestone-created');
    }

    public function update(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        abort_unless($milestone->project_id === $project->id, 404);

        if (! $milestone->isPending()) {
            throw ValidationException::withMessages(['milestone' => __('Invoiced milestones can\'t be changed. Void the invoice to unlock it.')]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'due_date' => ['nullable', 'date'],
        ]);

        $this->ensureWithinFixedFee($project, (float) $validated['amount'], $milestone);

        $milestone->update($validated);

        return redirect()->route('projects.show', $project)->with('status', 'milestone-updated');
    }

    public function destroy(Project $project, Milestone $milestone): RedirectResponse
    {
        abort_unless($milestone->project_id === $project->id, 404);

        if (! $milestone->isPending()) {
            throw ValidationException::withMessages(['milestone' => __('Invoiced milestones can\'t be deleted.')]);
        }

        $milestone->delete();

        return redirect()->route('projects.show', $project)->with('status', 'milestone-deleted');
    }

    /**
     * Milestone amounts can't add up to more than the fixed fee.
     */
    private function ensureWithinFixedFee(Project $project, float $amount, ?Milestone $ignore = null): void
    {
        $others = (float) $project->milestones()
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->sum('amount');

        if ($others + $amount > (float) $project->fixed_fee_total + 0.001) {
            throw ValidationException::withMessages([
                'amount' => __('Milestones would total :total, more than the fixed fee of :fee.', [
                    'total' => number_format($others + $amount, 2),
                    'fee' => number_format((float) $project->fixed_fee_total, 2),
                ]),
            ]);
        }
    }
}
