<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Subject extends Model
{
    protected $fillable = ['code', 'name', 'school_class_id', 'arm_id', 'status'];

    /** Create or reuse one subject record and associate it with each selected class. */
    public static function createForClasses(array $attributes, array $classIds): Collection
    {
        $classIds = static::validatedClassIds($classIds);
        $name = trim((string) ($attributes['name'] ?? ''));
        $code = trim((string) ($attributes['code'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'A subject name is required.']);
        }
        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'A subject code is required.']);
        }
        if ($classIds->isEmpty()) {
            throw ValidationException::withMessages(['class_ids' => 'Select at least one class.']);
        }

        return DB::transaction(function () use ($attributes, $classIds, $name, $code): Collection {
            $subject = static::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($name)])
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($subject) {
                if (strcasecmp(trim((string) $subject->code), $code) !== 0 && ! static::legacyCodeMatchesBase($subject, $code)) {
                    throw ValidationException::withMessages([
                        'code' => 'A subject with this name already exists with code '.($subject->code ?: '(no code)').'. Edit that subject to update its class assignments.',
                    ]);
                }
            } else {
                $codeOwner = static::query()->where('code', $code)->first();
                if ($codeOwner) {
                    throw ValidationException::withMessages(['code' => 'This subject code is already in use.']);
                }

                $subject = static::query()->create([
                    'code' => $code,
                    'name' => $name,
                    'status' => $attributes['status'] ?? 'active',
                ]);
            }

            $subject->syncClassAssignments($classIds->all(), $attributes['arm_id'] ?? null);

            return collect([$subject->refresh()]);
        });
    }

    /** @param array<int, int|string> $classIds */
    public function syncClassAssignments(array $classIds, int|string|null $armId = null): void
    {
        $classIds = static::validatedClassIds($classIds);
        if ($classIds->isEmpty()) {
            throw ValidationException::withMessages(['class_ids' => 'Select at least one class.']);
        }

        if ($armId !== null) {
            $armId = (int) $armId;
            foreach ($classIds as $classId) {
                if (! SchoolClass::query()->whereKey($classId)->whereHas('arms', fn ($query) => $query->whereKey($armId))->exists()) {
                    throw ValidationException::withMessages(['arm_id' => 'The selected arm must belong to every selected class.']);
                }
            }
        }

        $pivot = $classIds->mapWithKeys(fn (int $classId): array => [
            $classId => ['arm_id' => $armId, 'created_at' => now(), 'updated_at' => now()],
        ])->all();

        $this->classes()->sync($pivot);
    }

    /** @param array<int, int|string> $classIds @return Collection<int, int> */
    private static function validatedClassIds(array $classIds): Collection
    {
        $classIds = collect($classIds)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($classIds->isNotEmpty()) {
            $classes = SchoolClass::query()->whereIn('id', $classIds)->orderBy('id')->get();
            if ($classes->count() !== $classIds->count()) {
                throw ValidationException::withMessages(['class_ids' => 'One or more selected classes are no longer available.']);
            }
        }

        return $classIds;
    }

    public function appliesToClass(?int $classId, ?int $armId = null): bool
    {
        if ($classId === null) {
            return $this->school_class_id === null && $this->arm_id === null;
        }

        return static::query()->whereKey($this->getKey())->applicableTo($classId, $armId)->exists();
    }

    public function assignedToClass(int $classId): bool
    {
        return static::query()->whereKey($this->getKey())->forClass($classId)->exists();
    }

    public function scopeForClass($query, int $classId)
    {
        return $query->where(function ($subjects) use ($classId): void {
            $subjects->whereHas('classAssignments', fn ($assignments) => $assignments->where('school_class_id', $classId))
                ->orWhere(function ($legacy) use ($classId): void {
                    $legacy->where('school_class_id', $classId)->whereDoesntHave('classAssignments');
                })
                ->orWhere(function ($legacy) use ($classId): void {
                    $legacy->whereNull('school_class_id')->whereNull('arm_id')->whereDoesntHave('classAssignments');
                });
        });
    }

    public function scopeApplicableTo($query, ?int $classId, ?int $armId = null)
    {
        if ($classId === null) {
            return $query->whereNull('school_class_id')->whereNull('arm_id')->whereDoesntHave('classAssignments');
        }

        return $query->where(function ($subjects) use ($classId, $armId): void {
            $subjects->whereHas('classAssignments', function ($assignments) use ($classId, $armId): void {
                $assignments->where('school_class_id', $classId)
                    ->where(fn ($arms) => $arms->whereNull('arm_id')->when($armId, fn ($armQuery) => $armQuery->orWhere('arm_id', $armId)));
            })->orWhere(function ($legacy) use ($classId, $armId): void {
                $legacy->whereNull('school_class_id')->whereNull('arm_id')->whereDoesntHave('classAssignments');
            })->orWhere(function ($legacy) use ($classId, $armId): void {
                $legacy->where('school_class_id', $classId)->whereDoesntHave('classAssignments')
                    ->where(fn ($arms) => $arms->whereNull('arm_id')->when($armId, fn ($armQuery) => $armQuery->orWhere('arm_id', $armId)));
            });
        });
    }

    /** @return Collection<int, self> Legacy helper retained for integrations during transition. */
    public static function createLegacyClassCopies(array $attributes, array $classIds): Collection
    {
        return static::createForClasses($attributes, $classIds);
    }

    protected static function booted(): void
    {
        static::saving(function (self $subject): void {
            if (! in_array($subject->status, ['active', 'inactive'], true)) {
                throw ValidationException::withMessages(['status' => 'Choose a valid subject status.']);
            }

            if ($subject->arm_id && (! $subject->school_class_id || ! SchoolClass::query()->whereKey($subject->school_class_id)->whereHas('arms', fn ($query) => $query->whereKey($subject->arm_id))->exists())) {
                throw ValidationException::withMessages(['arm_id' => 'Choose an arm belonging to the selected class.']);
            }

            if ($subject->code && static::query()->where('code', $subject->code)
                ->when($subject->exists, fn ($query) => $query->whereKeyNot($subject->getKey()))
                ->exists()) {
                throw ValidationException::withMessages(['code' => 'This subject code is already in use.']);
            }

            if (! $subject->exists && $subject->school_class_id === null && $subject->name !== null
                && static::query()->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($subject->name))])->exists()) {
                throw ValidationException::withMessages(['name' => 'A subject with this name already exists. Assign classes to the existing subject instead.']);
            }

            if ($subject->exists && $subject->isDirty('name') && $subject->name !== null
                && static::query()->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($subject->name))])
                    ->whereKeyNot($subject->getKey())->exists()) {
                throw ValidationException::withMessages(['name' => 'Another subject already uses this name. Edit its class assignments instead.']);
            }
        });
    }

    private static function legacyCodeMatchesBase(self $subject, string $code): bool
    {
        if (! $subject->school_class_id || ! $subject->code) {
            return false;
        }

        $className = SchoolClass::query()->whereKey($subject->school_class_id)->value('name');
        if (! $className) {
            return false;
        }

        $suffix = '-'.Str::upper(Str::slug((string) $className, ''));

        return Str::endsWith(Str::upper((string) $subject->code), $suffix)
            && strcasecmp(substr((string) $subject->code, 0, -strlen($suffix)), $code) === 0;
    }

    public function schoolClass() { return $this->belongsTo(SchoolClass::class); }
    public function arm() { return $this->belongsTo(Arm::class); }
    public function classes() { return $this->belongsToMany(SchoolClass::class, 'subject_class_assignments', 'subject_id', 'school_class_id')->withPivot('arm_id')->withTimestamps(); }
    public function classAssignments() { return $this->hasMany(SubjectClassAssignment::class); }
    public function teacherAssignments() { return $this->hasMany(TeacherAssignment::class); }
    public function results() { return $this->hasMany(Result::class); }
}
