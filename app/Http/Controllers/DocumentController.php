<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachDocumentRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(): View
    {
        $documents = Document::query()
            ->with(['documentable', 'uploadedBy'])
            ->latest()
            ->paginate(20);

        return view('documents.index', [
            'documents' => $documents,
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $displayName = $request->filled('name') ? $request->string('name')->toString() : $originalName;

        $extension = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin');
        $storageFilename = Str::uuid().'.'.$extension;
        $storageDir = (string) Str::uuid();
        $path = $storageDir.'/'.$storageFilename;

        $stream = fopen($file->getPathname(), 'rb');

        try {
            Storage::disk('s3')->put($path, $stream, ['ContentType' => $file->getMimeType() ?? 'application/octet-stream']);
        } catch (\Throwable $e) {
            Log::error('S3 document upload failed', ['path' => $path, 'error' => $e->getMessage()]);

            return back()->withErrors(['file' => __('The file could not be uploaded: :error', ['error' => $e->getMessage()])]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $documentable = null;
        if ($request->filled('documentable_type') && $request->filled('documentable_uuid')) {
            $documentable = $this->resolveDocumentable(
                $request->string('documentable_type')->toString(),
                $request->string('documentable_uuid')->toString(),
            );
        }

        $document = Document::query()->create([
            'name' => $displayName,
            'disk' => 's3',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'documentable_type' => $documentable ? get_class($documentable) : null,
            'documentable_id' => $documentable?->getKey(),
            'user_id' => Auth::id(),
        ]);

        $redirectBack = $request->input('redirect_back');

        if ($redirectBack === 'transaction' && $documentable instanceof Transaction) {
            return redirect()
                ->route('transactions.show', $documentable)
                ->with('status', 'document-uploaded');
        }

        if ($redirectBack === 'project' && $documentable instanceof Project) {
            return redirect()
                ->route('projects.show', $documentable)
                ->with('status', 'document-uploaded');
        }

        if ($redirectBack === 'time-entry' && $documentable instanceof TimeEntry) {
            return redirect()
                ->route('time-entries.show', $documentable)
                ->with('status', 'document-uploaded');
        }

        return redirect()
            ->route('documents.index')
            ->with('status', 'document-uploaded');
    }

    public function edit(Document $document): View
    {
        return view('documents.edit', [
            'document' => $document,
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $document->update(['name' => $request->string('name')->toString()]);

        return redirect()
            ->route('documents.index')
            ->with('status', 'document-updated');
    }

    public function download(Document $document): RedirectResponse
    {
        $url = Storage::disk($document->disk)->temporaryUrl(
            $document->path,
            now()->addMinutes(5),
            ['ResponseContentDisposition' => 'attachment; filename="'.rawurlencode($document->name).'"'],
        );

        return redirect()->away($url);
    }

    public function preview(Document $document): RedirectResponse
    {
        $url = Storage::disk($document->disk)->temporaryUrl(
            $document->path,
            now()->addMinutes(5),
            ['ResponseContentDisposition' => 'inline; filename="'.rawurlencode($document->name).'"'],
        );

        return redirect()->away($url);
    }

    public function destroy(Document $document): RedirectResponse
    {
        $redirectBack = request()->input('redirect_back');
        $documentable = $document->documentable;

        $document->delete();

        if ($redirectBack === 'transaction' && $documentable instanceof Transaction) {
            return redirect()
                ->route('transactions.show', $documentable)
                ->with('status', 'document-deleted');
        }

        if ($redirectBack === 'project' && $documentable instanceof Project) {
            return redirect()
                ->route('projects.show', $documentable)
                ->with('status', 'document-deleted');
        }

        if ($redirectBack === 'time-entry' && $documentable instanceof TimeEntry) {
            return redirect()
                ->route('time-entries.show', $documentable)
                ->with('status', 'document-deleted');
        }

        return redirect()
            ->route('documents.index')
            ->with('status', 'document-deleted');
    }

    public function attach(AttachDocumentRequest $request, Document $document): RedirectResponse
    {
        $documentable = $this->resolveDocumentable(
            $request->string('documentable_type')->toString(),
            $request->string('documentable_uuid')->toString(),
        );

        if (! $documentable) {
            return back()->withErrors(['documentable_uuid' => __('The selected record could not be found.')]);
        }

        $document->update([
            'documentable_type' => get_class($documentable),
            'documentable_id' => $documentable->getKey(),
        ]);

        $redirectBack = $request->input('redirect_back');

        if ($redirectBack === 'transaction' && $documentable instanceof Transaction) {
            return redirect()
                ->route('transactions.show', $documentable)
                ->with('status', 'document-attached');
        }

        if ($redirectBack === 'project' && $documentable instanceof Project) {
            return redirect()
                ->route('projects.show', $documentable)
                ->with('status', 'document-attached');
        }

        if ($redirectBack === 'time-entry' && $documentable instanceof TimeEntry) {
            return redirect()
                ->route('time-entries.show', $documentable)
                ->with('status', 'document-attached');
        }

        return redirect()
            ->route('documents.index')
            ->with('status', 'document-attached');
    }

    private function resolveDocumentable(string $type, string $uuid): ?object
    {
        return match ($type) {
            'transaction' => Transaction::query()->where('uuid', $uuid)->first(),
            'project' => Project::query()->where('uuid', $uuid)->first(),
            'time-entry' => TimeEntry::query()->where('uuid', $uuid)->first(),
            default => null,
        };
    }
}
