<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Database\Seeders\DemoReportCardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoReportCardSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_demo_report_seeder_restores_a_soft_deleted_demo_student(): void
    {
        $this->seed();

        $student = Student::query()->create([
            'student_number' => 'ASC-DEMO-2627-001',
            'first_name' => 'Old',
            'last_name' => 'Demo',
            'status' => 'inactive',
        ]);
        $student->delete();

        $this->seed(DemoReportCardSeeder::class);

        $restored = Student::withTrashed()->findOrFail($student->id);
        $this->assertFalse($restored->trashed());
        $this->assertSame('Demo', $restored->first_name);
        $this->assertSame('Student', $restored->last_name);
        $this->assertSame(1, Student::withTrashed()->where('student_number', 'ASC-DEMO-2627-001')->count());
    }

    #[Test]
    public function rerunning_the_demo_seeder_removes_stale_first_term_results_and_keeps_eight_subjects(): void
    {
        $this->seed(DemoReportCardSeeder::class);

        $student = Student::query()->where('student_number', 'ASC-DEMO-2627-001')->firstOrFail();
        $session = AcademicSession::query()->where('name', '2026/2027')->firstOrFail();
        $firstTerm = AcademicTerm::query()
            ->where('academic_session_id', $session->id)
            ->where('sequence', 1)
            ->firstOrFail();
        $class = SchoolClass::query()->findOrFail($student->school_class_id);

        $staleSubject = Subject::query()->firstOrCreate(
            ['name' => 'Digital Technology', 'school_class_id' => $class->id, 'arm_id' => null],
            ['code' => 'STALE-DEMO-DIGITAL', 'status' => 'active'],
        );

        $result = [
            'student_id' => $student->id,
            'subject_id' => $staleSubject->id,
            'school_class_id' => $class->id,
            'arm_id' => null,
            'academic_session_id' => $session->id,
            'ca1_score' => 25,
            'ca2_score' => 0,
            'ca3_score' => 0,
            'exam_score' => 45,
            'total_score' => 70,
            'is_offered' => true,
            'grade' => 'B',
            'remark' => 'Very Good',
            'status' => 'published',
            'published_at' => now(),
        ];

        Result::query()->create($result + ['academic_term_id' => $firstTerm->id]);

        $secondTerm = AcademicTerm::query()->create([
            'academic_session_id' => $session->id,
            'name' => 'Second Term',
            'sequence' => 2,
            'is_current' => false,
        ]);
        $secondTermResult = Result::query()->create($result + ['academic_term_id' => $secondTerm->id]);

        $this->seed(DemoReportCardSeeder::class);

        $firstTermResults = Result::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('academic_term_id', $firstTerm->id)
            ->with('subject')
            ->get();

        $this->assertCount(8, $firstTermResults);
        $this->assertEqualsCanonicalizing([
            'Mathematics',
            'English Language',
            'Biology',
            'Chemistry',
            'Physics',
            'Agricultural Science',
            'Computer Studies',
            'Civic Education',
        ], $firstTermResults->pluck('subject.name')->all());
        $this->assertDatabaseHas('results', [
            'id' => $secondTermResult->id,
            'student_id' => $student->id,
            'academic_term_id' => $secondTerm->id,
            'subject_id' => $staleSubject->id,
        ]);
    }
}
