<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Arm;
use App\Models\ParentProfile;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Filament\Resources\SubjectResource\Pages\CreateSubject;
use App\Filament\Resources\SubjectResource\Pages\EditSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Livewire\Livewire;
use Tests\TestCase;

class StaffResultsTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;
    private SchoolClass $class;
    private SchoolClass $otherClass;
    private Subject $maths;
    private Subject $english;
    private Student $student;
    private Student $otherStudent;
    private User $teacher;
    private TeacherAssignment $assignment;
    private User $resultsAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->withoutVite();
        $this->seed();

        $session = AcademicSession::query()->create(['name' => '2026/2027', 'is_active' => true]);
        $this->term = AcademicTerm::query()->create(['academic_session_id' => $session->id, 'name' => 'First Term', 'sequence' => 1, 'is_current' => true]);
        $this->class = SchoolClass::query()->create(['name' => 'JSS1', 'is_active' => true]);
        $this->otherClass = SchoolClass::query()->create(['name' => 'JSS2', 'is_active' => true]);
        $arm = Arm::query()->create(['name' => 'R']);
        $this->class->arms()->attach($arm->id);
        $this->maths = Subject::query()->create(['name' => 'Mathematics', 'status' => 'active']);
        $this->english = Subject::query()->create(['name' => 'English', 'status' => 'active']);
        $this->student = Student::query()->create(['student_number' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Okoro', 'school_class_id' => $this->class->id, 'arm_id' => $arm->id, 'status' => 'active']);
        $this->otherStudent = Student::query()->create(['student_number' => 'S2', 'first_name' => 'Bola', 'last_name' => 'Ajayi', 'school_class_id' => $this->otherClass->id, 'status' => 'active']);

        $this->teacher = User::factory()->create();
        $this->teacher->assignRole('Teacher');
        $teacherProfile = Teacher::query()->create(['user_id' => $this->teacher->id, 'first_name' => 'Tola', 'last_name' => 'Bello', 'status' => 'active']);
        $this->assignment = TeacherAssignment::query()->create(['teacher_id' => $teacherProfile->id, 'school_class_id' => $this->class->id, 'arm_id' => null, 'subject_id' => $this->maths->id]);

        $this->resultsAdmin = User::factory()->create();
        $this->resultsAdmin->assignRole('Results Admin');
    }

    #[Test]
    public function a_teacher_can_save_scores_for_an_assigned_class_and_subject(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('staff.results.subject.save', $this->assignment), [
            'term_id' => $this->term->id,
            'rows' => [$this->student->id => ['offered' => '1', 'ca1' => '30', 'exam' => '50']],
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();

        $result = Result::query()->where('student_id', $this->student->id)->where('subject_id', $this->maths->id)->firstOrFail();
        $this->assertSame('pending', $result->status);
        $this->assertEquals(80.0, (float) $result->total_score);
    }

    #[Test]
    public function one_subject_record_can_be_assigned_to_multiple_classes_and_reused(): void
    {
        $created = Subject::createForClasses([
            'code' => 'BIO',
            'name' => 'Biology',
            'status' => 'active',
        ], [$this->class->id, $this->otherClass->id]);

        $this->assertCount(1, $created);
        $subject = $created->first();
        $this->assertNull($subject->school_class_id);
        $this->assertSame('BIO', $subject->code);
        $this->assertEqualsCanonicalizing([$this->class->id, $this->otherClass->id], $subject->classes()->pluck('school_classes.id')->all());

        $retry = Subject::createForClasses([
            'code' => 'BIO',
            'name' => 'Biology',
            'status' => 'active',
        ], [$this->class->id, $this->otherClass->id]);

        $this->assertCount(1, $retry);
        $this->assertSame($subject->id, $retry->first()->id);
        $this->assertSame(1, Subject::query()->where('name', 'Biology')->count());
    }

    #[Test]
    public function the_subject_create_form_creates_one_subject_for_all_selected_classes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(CreateSubject::class)
            ->set('data.code', 'BIO')
            ->set('data.name', 'Biology')
            ->set('data.arm_id', null)
            ->set('data.status', 'active')
            ->set('data.class_ids', [$this->class->id, $this->otherClass->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $subject = Subject::query()->where('name', 'Biology')->firstOrFail();
        $this->assertSame(1, Subject::query()->where('name', 'Biology')->count());
        $this->assertEqualsCanonicalizing(
            [$this->class->id, $this->otherClass->id],
            $subject->classes()->pluck('school_classes.id')->all(),
        );
    }

    #[Test]
    public function editing_a_subject_updates_its_class_relationships_without_creating_a_copy(): void
    {
        $subject = Subject::createForClasses([
            'code' => 'CHEM',
            'name' => 'Chemistry',
            'status' => 'active',
        ], [$this->class->id])->first();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(EditSubject::class, ['record' => $subject->id])
            ->fillForm([
                'code' => 'CHEM',
                'name' => 'Chemistry',
                'class_ids' => [$this->otherClass->id],
                'arm_id' => null,
                'status' => 'active',
            ])
            ->set('data.class_ids', [$this->otherClass->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('subject_class_assignments', ['subject_id' => $subject->id, 'school_class_id' => $this->otherClass->id, 'arm_id' => null]);
        $this->assertDatabaseMissing('subject_class_assignments', ['subject_id' => $subject->id, 'school_class_id' => $this->class->id]);
        $this->assertSame(1, Subject::query()->where('name', 'Chemistry')->count());
    }

    #[Test]
    public function an_optional_arm_is_validated_against_the_assigned_class(): void
    {
        $arm = Arm::query()->create(['name' => 'Blue']);
        $this->class->arms()->attach($arm->id);
        $subject = Subject::createForClasses([
            'code' => 'BIO',
            'name' => 'Biology',
            'status' => 'active',
            'arm_id' => $arm->id,
        ], [$this->class->id])->first();

        $this->assertTrue($subject->appliesToClass($this->class->id, $arm->id));
        $this->assertFalse($subject->appliesToClass($this->class->id, null));
        $this->assertFalse($subject->appliesToClass($this->otherClass->id, $arm->id));
    }

    #[Test]
    public function a_teacher_cannot_enter_scores_for_a_class_they_do_not_teach(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('staff.results.student', ['student' => $this->otherStudent->id]))
            ->assertForbidden();

        $this->actingAs($this->teacher)->get(route('staff.results.class', ['class' => $this->otherClass->id]))->assertForbidden();
    }

    #[Test]
    public function a_teacher_cannot_open_another_teachers_score_sheet(): void
    {
        $other = User::factory()->create();
        $other->assignRole('Teacher');
        Teacher::query()->create(['user_id' => $other->id, 'first_name' => 'Kemi', 'last_name' => 'Ola', 'status' => 'active']);

        $this->actingAs($other)->get(route('staff.results.subject', $this->assignment))->assertForbidden();
        $this->actingAs($other)->post(route('staff.results.subject.save', $this->assignment), ['term_id' => $this->term->id, 'rows' => [$this->student->id => ['ca1' => '10']]])->assertForbidden();
    }

    #[Test]
    public function scores_above_the_maximum_are_rejected(): void
    {
        $this->actingAs($this->teacher)->post(route('staff.results.subject.save', $this->assignment), [
            'term_id' => $this->term->id,
            'rows' => [$this->student->id => ['offered' => '1', 'ca1' => '55', 'exam' => '10']],
        ])->assertSessionHasErrors();

        $this->assertSame(0, Result::query()->count());
    }

    #[Test]
    public function students_and_parents_cannot_reach_the_staff_area(): void
    {
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Student');
        $this->actingAs($studentUser)->get(route('staff.results'))->assertForbidden();

        $parentUser = User::factory()->create();
        $parentUser->assignRole('Parent');
        $this->actingAs($parentUser)->get(route('staff.results.archive'))->assertForbidden();
    }

    #[Test]
    public function only_published_results_reach_the_linked_parent_and_unlinked_parents_are_refused(): void
    {
        $this->actingAs($this->teacher)->post(route('staff.results.subject.save', $this->assignment), [
            'term_id' => $this->term->id,
            'rows' => [$this->student->id => ['offered' => '1', 'ca1' => '30', 'exam' => '50']],
        ])->assertRedirect();

        $parentUser = User::factory()->create();
        $parentUser->assignRole('Parent');
        $parent = ParentProfile::query()->create(['user_id' => $parentUser->id, 'full_name' => 'Mr Okoro']);
        $parent->students()->attach($this->student->id);

        $stranger = User::factory()->create();
        $stranger->assignRole('Parent');
        ParentProfile::query()->create(['user_id' => $stranger->id, 'full_name' => 'Someone Else']);

        $url = route('reports.show', ['student' => $this->student->id, 'term' => $this->term->id]);

        // Pending: families cannot see it.
        $this->actingAs($parentUser)->get($url)->assertNotFound();

        // A results administrator publishes the class.
        $this->actingAs($this->resultsAdmin)->post(route('staff.results.publish-class'), [
            'class_id' => $this->class->id, 'term_id' => $this->term->id,
        ])->assertRedirect();

        $this->actingAs($parentUser)->get($url)->assertOk()->assertSee('Okoro');
        $this->actingAs($stranger)->get($url)->assertForbidden();
    }

    #[Test]
    public function cumulative_report_cards_include_only_results_through_the_selected_term(): void
    {
        $secondTerm = AcademicTerm::query()->create([
            'academic_session_id' => $this->term->academic_session_id,
            'name' => 'Second Term',
            'sequence' => 2,
            'is_current' => false,
        ]);
        $thirdTerm = AcademicTerm::query()->create([
            'academic_session_id' => $this->term->academic_session_id,
            'name' => 'Third Term',
            'sequence' => 3,
            'is_current' => false,
        ]);
        $results = [
            1 => $this->publishedResultFor($this->term, 60),
            2 => $this->publishedResultFor($secondTerm, 80),
            3 => $this->publishedResultFor($thirdTerm, 100),
        ];
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
        $this->otherClass->arms()->attach($this->student->arm_id);
        $this->student->update(['school_class_id' => $this->otherClass->id]);

        $this->get(route('reports.show', ['student' => $this->student->id, 'term' => $this->term->id]))
            ->assertOk()
            ->assertSee('JSS1')
            ->assertDontSee('JSS2')
            ->assertDontSee('Cumulative total');

        $this->get(route('reports.show', ['student' => $this->student->id, 'term' => $secondTerm->id]))
            ->assertOk()
            ->assertSee('JSS1')
            ->assertDontSee('JSS2')
            ->assertSee('Cumulative total')
            ->assertSee('140')
            ->assertSee('70%');

        $this->get(route('reports.show', ['student' => $this->student->id, 'term' => $thirdTerm->id]))
            ->assertOk()
            ->assertSee('Cumulative total')
            ->assertSee('240')
            ->assertSee('80%');

        // The older single-result link now hands over to the one report page.
        $this->get(route('results.report', ['result' => $results[2]->id]))
            ->assertRedirect(route('reports.show', ['student' => $this->student->id, 'term' => $secondTerm->id]));
    }

    #[Test]
    public function an_admin_can_view_and_download_a_ranked_class_broadsheet(): void
    {
        $secondStudent = Student::query()->create([
            'student_number' => 'S3',
            'first_name' => 'Chika',
            'last_name' => 'Nwosu',
            'school_class_id' => $this->class->id,
            'arm_id' => $this->student->arm_id,
            'status' => 'active',
        ]);
        $this->publishedResultFor($this->term, 80, $this->student, $this->maths);
        $this->publishedResultFor($this->term, 70, $this->student, $this->english);
        $this->publishedResultFor($this->term, 60, $secondStudent, $this->maths);
        $this->publishedResultFor($this->term, 50, $secondStudent, $this->english);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $filters = [
            'session' => $this->term->academic_session_id,
            'term' => $this->term->id,
            'class' => $this->class->id,
            'arm' => $this->student->arm_id,
        ];

        $this->actingAs($admin)->get(route('staff.results.broadsheet', $filters))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('English')
            ->assertSee('Overall position')
            ->assertSee('Okoro, Ada')
            ->assertSee('1 of 2');

        $this->get(route('staff.results.broadsheet', $filters + ['download' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function a_teacher_broadsheet_is_limited_to_assigned_subjects_and_classes(): void
    {
        $this->publishedResultFor($this->term, 80, $this->student, $this->maths);
        $this->publishedResultFor($this->term, 70, $this->student, $this->english);
        $filters = [
            'session' => $this->term->academic_session_id,
            'term' => $this->term->id,
            'class' => $this->class->id,
            'arm' => $this->student->arm_id,
        ];

        $this->actingAs($this->teacher)->get(route('staff.results.broadsheet', $filters))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertDontSee('English')
            ->assertSee('overall rank is intentionally not shown');

        $this->get(route('staff.results.broadsheet', array_merge($filters, ['class' => $this->otherClass->id, 'arm' => null])))
            ->assertForbidden();
    }

    #[Test]
    public function teacher_score_sheets_and_full_report_routes_respect_subject_scope(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('staff.results.student', ['student' => $this->student->id, 'term' => $this->term->id]))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertDontSee('English')
            ->assertDontSee('Print result');

        $result = $this->publishedResultFor($this->term, 80, $this->student, $this->maths);
        $this->get(route('reports.show', ['student' => $this->student->id, 'term' => $this->term->id]))
            ->assertForbidden();
        $this->get(route('results.report', ['result' => $result->id]))
            ->assertForbidden();
    }

    #[Test]
    public function a_published_line_cannot_be_changed_by_a_teacher(): void
    {
        $this->actingAs($this->teacher)->post(route('staff.results.subject.save', $this->assignment), [
            'term_id' => $this->term->id,
            'rows' => [$this->student->id => ['offered' => '1', 'ca1' => '30', 'exam' => '50']],
        ]);
        $this->actingAs($this->resultsAdmin)->post(route('staff.results.publish-class'), ['class_id' => $this->class->id, 'term_id' => $this->term->id]);

        $this->actingAs($this->teacher)->post(route('staff.results.subject.save', $this->assignment), [
            'term_id' => $this->term->id,
            'rows' => [$this->student->id => ['offered' => '1', 'ca1' => '1', 'exam' => '1']],
        ])->assertRedirect();

        $this->assertEquals(80.0, (float) Result::query()->where('student_id', $this->student->id)->value('total_score'));
    }

    #[Test]
    public function a_teacher_cannot_publish(): void
    {
        $this->actingAs($this->teacher)->post(route('staff.results.publish-class'), ['class_id' => $this->class->id, 'term_id' => $this->term->id])->assertForbidden();
    }

    #[Test]
    public function the_footer_links_to_the_admin_login(): void
    {
        $this->get('/')->assertOk()->assertSee('Admin login');
    }

    private function publishedResultFor(AcademicTerm $term, int $score, ?Student $student = null, ?Subject $subject = null): Result
    {
        $student ??= $this->student;
        $subject ??= $this->maths;

        return Result::query()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'school_class_id' => $this->class->id,
            'arm_id' => $student->arm_id,
            'academic_session_id' => $term->academic_session_id,
            'academic_term_id' => $term->id,
            'ca1_score' => $score * 0.4,
            'ca2_score' => 0,
            'ca3_score' => 0,
            'exam_score' => $score * 0.6,
            'total_score' => $score,
            'is_offered' => true,
            'grade' => 'B',
            'remark' => 'Very Good',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
