<?php

namespace Database\Seeders;

use App\Domain\Results\Services\ResultCalculator;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\AffectiveRating;
use App\Models\AffectiveTrait;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\SchoolSettings;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Creates clearly identified synthetic login data to demonstrate the existing results workflow. */
class DemoReportCardSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $settings = SchoolSettings::current();
            $settings->forceFill(['student_id_prefix' => 'ASC'])->save();

            $session = AcademicSession::query()->firstOrCreate(
                ['name' => '2026/2027'],
                ['is_active' => true],
            );
            if (! $session->is_active && ! AcademicSession::query()->where('is_active', true)->exists()) {
                $session->forceFill(['is_active' => true])->save();
            }
            $term = AcademicTerm::query()->firstOrCreate(
                ['academic_session_id' => $session->id, 'sequence' => 1],
                ['name' => 'First Term', 'is_current' => true],
            );

            $class = SchoolClass::query()->firstOrCreate(
                ['name' => 'SS1'],
                ['level' => 'senior secondary', 'is_active' => true],
            );

            $student = Student::withTrashed()->updateOrCreate(
                ['student_number' => 'ASC-DEMO-2627-001'],
                [
                    'first_name' => 'Demo',
                    'last_name' => 'Student',
                    'school_class_id' => $class->id,
                    'status' => 'active',
                ],
            );
            if ($student->trashed()) {
                $student->restore();
            }

            // These eight subjects illustrate the results system, using only subjects named in the supplied profile.
            // The school's final list and these sample marks must be confirmed before treating them as real records.
            $marks = [
                'Mathematics' => [34, 53],
                'English Language' => [32, 51],
                'Biology' => [35, 56],
                'Chemistry' => [31, 52],
                'Physics' => [30, 49],
                'Agricultural Science' => [33, 54],
                'Computer Studies' => [36, 58],
                'Civic Education' => [32, 55],
            ];
            $calculator = new ResultCalculator();
            foreach ($marks as $name => [$ca1, $exam]) {
                $subject = Subject::query()->firstOrCreate(
                    ['name' => $name, 'school_class_id' => $class->id, 'arm_id' => null],
                    ['code' => 'DEMO-'.strtoupper(substr(sha1($name), 0, 7)), 'status' => 'active'],
                );
                $calculated = $calculator->calculate(['ca1' => $ca1, 'exam' => $exam]);
                Result::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'academic_session_id' => $session->id,
                        'academic_term_id' => $term->id,
                    ],
                    [
                        'school_class_id' => $class->id,
                        'ca1_score' => $ca1,
                        'ca2_score' => 0,
                        'ca3_score' => 0,
                        'exam_score' => $exam,
                        'total_score' => $calculated['total_score'],
                        'is_offered' => true,
                        'grade' => $calculated['grade'],
                        'remark' => $calculated['remark'],
                        'teacher_remark' => 'Sample result for website demonstration only.',
                        'status' => 'published',
                        'published_at' => now(),
                    ],
                );
            }

            $ratings = [
                'Attendance' => 4,
                'Attentiveness' => 5,
                'Honesty' => 5,
                'Industriousness' => 4,
                'Initiative' => 4,
                'Neatness' => 4,
                'Relationship with others' => 5,
                'Handwriting' => 4,
                'Punctuality' => 5,
            ];
            foreach ($ratings as $name => $value) {
                $trait = AffectiveTrait::query()->firstOrCreate(
                    ['name' => $name],
                    ['category' => 'affective', 'is_active' => true, 'sort_order' => count($ratings)],
                );
                AffectiveRating::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'trait_id' => $trait->id,
                        'academic_session_id' => $session->id,
                        'academic_term_id' => $term->id,
                    ],
                    ['rating' => $value],
                );
            }
        });
    }
}
