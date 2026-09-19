<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('categories')->nullOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('categories')->nullOnDelete();
        });

        $vendorCategoryIds = DB::table('categories')->where('type', 'vendor')->pluck('id', 'code');
        $serviceCategoryIds = DB::table('categories')->where('type', 'service')->pluck('id', 'code');

        foreach (DB::table('vendors')->select('id', 'category')->get() as $vendor) {
            DB::table('vendors')
                ->where('id', $vendor->id)
                ->update(['category_id' => $vendorCategoryIds[$vendor->category] ?? $vendorCategoryIds['other']]);
        }

        foreach (DB::table('services')->select('id', 'category')->get() as $service) {
            DB::table('services')
                ->where('id', $service->id)
                ->update(['category_id' => $serviceCategoryIds[$service->category] ?? $serviceCategoryIds['other']]);
        }

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->enum('category', ['telco', 'cloud', 'saas', 'professional_services', 'utilities', 'other'])->nullable()->after('name');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->enum('category', ['storage', 'communication', 'design', 'dev_tools', 'security', 'productivity', 'other'])->nullable()->after('plan');
        });

        foreach (DB::table('vendors')->select('id', 'category_id')->get() as $vendor) {
            $code = DB::table('categories')->where('id', $vendor->category_id)->value('code');
            DB::table('vendors')->where('id', $vendor->id)->update(['category' => $code ?? 'other']);
        }

        foreach (DB::table('services')->select('id', 'category_id')->get() as $service) {
            $code = DB::table('categories')->where('id', $service->category_id)->value('code');
            DB::table('services')->where('id', $service->id)->update(['category' => $code ?? 'other']);
        }

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->index('category');
        });
    }
};
