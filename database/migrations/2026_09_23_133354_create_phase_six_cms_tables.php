<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40)->default('public');
            $table->text('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('alt_text')->nullable();
            $table->json('metadata')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'mime_type', 'created_at'], 'media_type_history');
        });

        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('og_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('slug', 191);
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('template', 60)->default('default');
            $table->string('status', 30)->default('DRAFT');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'published_at'], 'cms_pages_published');
        });

        Schema::create('cms_posts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('featured_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('slug', 191);
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('category', 100)->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'published_at'], 'cms_posts_published');
            $table->index(['tenant_id', 'category', 'published_at'], 'cms_posts_category');
        });

        Schema::create('cms_services', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 191);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->decimal('starting_price', 14, 2)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'sort_order'], 'cms_services_published');
        });

        Schema::create('cms_offers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->string('title');
            $table->string('code', 60)->nullable();
            $table->text('body');
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->timestamp('published_at')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'starts_on', 'ends_on'], 'cms_offers_active');
        });

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 160);
            $table->string('company_name')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('quote');
            $table->string('status', 30)->default('DRAFT');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'sort_order'], 'testimonials_published');
        });

        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->string('content_type', 100);
            $table->ulid('content_id');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignUlid('authored_by')->nullable()->constrained('users')->nullOnDelete();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'content_type', 'content_id', 'version'], 'content_revision_version');
            $table->index(['tenant_id', 'content_type', 'content_id', 'created_at'], 'content_revision_history');
        });

        Schema::create('booking_enquiries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('cms_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('converted_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('name', 160);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('source', 60)->default('WEBSITE');
            $table->string('status', 30)->default('NEW');
            $table->string('service_type', 120)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->text('message')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'created_at'], 'booking_enquiries_queue');
            $table->index(['tenant_id', 'source', 'created_at'], 'booking_enquiries_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_enquiries');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('cms_offers');
        Schema::dropIfExists('cms_services');
        Schema::dropIfExists('cms_posts');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('media_assets');
    }

    private function tenantKey(Blueprint $table): void
    {
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
    }

    private function businessTimestamps(Blueprint $table): void
    {
        $table->ulid('created_by')->nullable();
        $table->ulid('updated_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
    }
};
