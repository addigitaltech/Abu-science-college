<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove only stale result rows belonging to the public synthetic demo student.
     * The migration is intentionally guarded so it does nothing unless all eight
     * expected class-scoped subjects already exist for the demo term.
     */
    public function up(): void
    {
        $student = DB::table('students')
            ->where('student_number', 'ASC-DEMO-2627-001')
            ->first(['id', 'school_class_id']);

        if (! $student) {
            return;
        }

        $sessionId = DB::table('academic_sessions')
            ->where('name', '2026/2027')
            ->value('id');

        if (! $sessionId) {
            return;
        }

        $termId = DB::table('academic_terms')
            ->where('academic_session_id', $sessionId)
            ->where('sequence', 1)
            ->value('id');

        if (! $termId) {
            return;
        }

        $expectedSubjects = [
            'Mathematics',
            'English Language',
            'Biology',
            'Chemistry',
            'Physics',
            'Agricultural Science',
            'Computer Studies',
            'Civic Education',
        ];

        $subjectIds = [];

        foreach ($expectedSubjects as $name) {
            $subjectId = DB::table('subjects')
                ->where('name', $name)
                ->where('school_class_id', $student->school_class_id)
                ->whereNull('arm_id')
                ->orderBy('id')
                ->value('id');

            if (! $subjectId) {
                return;
            }

            $subjectIds[] = $subjectId;
        }

        DB::table('results')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $sessionId)
            ->where('academic_term_id', $termId)
            ->whereNotIn('subject_id', $subjectIds)
            ->delete();
    }

    public function down(): void
    {
        // Deleted synthetic demo results cannot be reconstructed by a rollback.
    }
};
