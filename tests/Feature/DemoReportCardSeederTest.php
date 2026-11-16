<?php

namespace Tests\Feature;

use App\Models\Student;
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
}
