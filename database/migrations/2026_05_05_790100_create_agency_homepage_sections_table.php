<?php

use App\Models\Agency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_homepage_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('section_key');
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->json('content')->nullable();
            $table->string('image_path')->nullable();
            $table->integer('sort_order')->default(100);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['agency_id', 'section_key']);
        });

        $sections = ['hero', 'trust_metrics', 'feature_cards', 'popular_routes', 'operator_preview', 'why_choose_us'];
        foreach (Agency::query()->pluck('id') as $agencyId) {
            foreach ($sections as $index => $section) {
                DB::table('agency_homepage_sections')->insert([
                    'agency_id' => $agencyId,
                    'section_key' => $section,
                    'sort_order' => ($index + 1) * 10,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_homepage_sections');
    }
};
