<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $vendorCategories = Category::query()->ofType('vendor')->orderBy('name')->get();
        $serviceCategories = Category::query()->ofType('service')->orderBy('name')->get();

        return view('settings.categories.index', [
            'vendorCategories' => $vendorCategories,
            'serviceCategories' => $serviceCategories,
        ]);
    }

    public function create(Request $request): View
    {
        $type = $request->query('type');

        abort_unless(in_array($type, ['vendor', 'service'], true), 404);

        return view('settings.categories.create', ['type' => $type]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        return redirect()
            ->route('settings.categories.index')
            ->with('status', 'category-created');
    }

    public function edit(Category $category): View
    {
        return view('settings.categories.edit', ['category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()
            ->route('settings.categories.index')
            ->with('status', 'category-updated');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->vendors()->exists() || $category->services()->exists()) {
            return redirect()
                ->route('settings.categories.index')
                ->with('status', 'category-in-use');
        }

        $category->delete();

        return redirect()
            ->route('settings.categories.index')
            ->with('status', 'category-deleted');
    }
}
