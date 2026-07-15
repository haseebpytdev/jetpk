<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 180)->unique();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->string('seo_title', 180)->nullable();
            $table->string('seo_description', 255)->nullable();
            $table->string('canonical_url', 255)->nullable();
            $table->string('robots', 16)->default('index');
            $table->string('status', 16)->default('draft');
            $table->boolean('show_in_footer')->default(false);
            $table->string('footer_group', 32)->nullable();
            $table->string('footer_label', 120)->nullable();
            $table->unsignedInteger('footer_sort_order')->default(0);
            $table->boolean('open_in_new_tab')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'show_in_footer', 'footer_sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
