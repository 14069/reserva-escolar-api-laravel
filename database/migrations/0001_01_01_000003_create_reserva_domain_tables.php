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
        Schema::create('resource_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('resource_categories')->insert([
            ['name' => 'audiovisual', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'chromebooks', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'espacos', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('resource_categories')->nullOnDelete();
            $table->string('name');
            $table->smallInteger('active')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'active']);
        });

        Schema::create('class_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->smallInteger('active')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'active']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->smallInteger('active')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'active']);
        });

        Schema::create('lesson_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->unsignedInteger('lesson_number');
            $table->string('label');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->smallInteger('active')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'lesson_number']);
            $table->index(['school_id', 'active']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->restrictOnDelete();
            $table->foreignId('class_group_id')->constrained('class_groups')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->date('booking_date');
            $table->string('status', 20)->default('scheduled');
            $table->text('purpose')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_feedback')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'booking_date']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'resource_id', 'booking_date']);
            $table->unique(['school_id', 'user_id', 'idempotency_key']);
        });

        Schema::create('booking_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('lesson_slot_id')->constrained('lesson_slots')->cascadeOnDelete();

            $table->unique(['booking_id', 'lesson_slot_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->text('metadata_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'user_id']);
            $table->index(['school_id', 'user_id', 'read_at']);
            $table->index(['school_id', 'user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('booking_lessons');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('lesson_slots');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('class_groups');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('resource_categories');
    }
};
