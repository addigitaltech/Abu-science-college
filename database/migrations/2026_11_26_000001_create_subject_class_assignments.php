<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subject_class_assignments')) {
            Schema::create('subject_class_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('subject_id')->constrained()->restrictOnDelete();
                $table->foreignId('school_class_id')->constrained('school_classes')->restrictOnDelete();
                $table->foreignId('arm_id')->nullable()->constrained('arms')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['subject_id', 'school_class_id'], 'subject_class_unique');
                $table->index(['school_class_id', 'arm_id']);
            });
        }

        // Backfill only: keep legacy columns, subject IDs, and all dependent records unchanged.
        // This is idempotent so it can be safely retried if a deployment is interrupted.
        DB::table('subjects')
            ->whereNotNull('school_class_id')
            ->orderBy('id')
            ->chunkById(500, function ($subjects): void {
                foreach ($subjects as $subject) {
                    $alreadyExists = DB::table('subject_class_assignments')
                        ->where('subject_id', $subject->id)
                        ->where('school_class_id', $subject->school_class_id)
                        ->where(function ($query) use ($subject): void {
                            $subject->arm_id === null
                                ? $query->whereNull('arm_id')
                                : $query->where('arm_id', $subject->arm_id);
                        })
                        ->exists();

                    if (! $alreadyExists) {
                        DB::table('subject_class_assignments')->insert([
                            'subject_id' => $subject->id,
                            'school_class_id' => $subject->school_class_id,
                            'arm_id' => $subject->arm_id,
                            'created_at' => $subject->created_at,
                            'updated_at' => $subject->updated_at,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Intentionally retain the additive assignment table and its data on rollback.
        // Existing releases may already have written assignments that are not represented
        // by the legacy subjects.school_class_id / subjects.arm_id columns.
    }
};
