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

    /**
     * Create one class-scoped subject record per selected class.
     * Existing matching records are skipped, making retries safe.
     *
     * @param array<string, mixed> $attributes
     * @param array<int, int|string> $classIds
     * @return Collection<int, self>
     */
    public static function createForClasses(array $attributes, array $classIds): Collection
    {
        $classIds = collect($classIds)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($classIds->isEmpty()) {
            return collect();
        }

        $classes = SchoolClass::query()->whereIn('id', $classIds)->orderBy('id')->get();
        if ($classes->count() !== $classIds->count()) {
            throw ValidationException::withMessages(['class_ids' => 'One or more selected classes are no longer available.']);
        }

        $created = collect();
        DB::transaction(function () use ($attributes, $classes, $created): void {
            $baseCode = trim((string) ($attributes['code'] ?? ''));
            $baseAttributes = collect($attributes)->only(['name', 'status'])->all();
            $armId = $attributes['arm_id'] ?? null;

            foreach ($classes as $class) {
                $existing = static::query()
                    ->where('name', $baseAttributes['name'] ?? '')
                    ->where('school_class_id', $class->id)
                    ->when($armId === null, fn ($query) => $query->whereNull('arm_id'), fn ($query) => $query->where('arm_id', $armId))
                    ->first();

                if ($existing) {
                    continue;
                }

                $code = $baseCode === '' ? null : static::uniqueClassCode($baseCode, (string) $class->name, (int) $class->id);
                $created->push(static::query()->create($baseAttributes + [
                    'code' => $code,
                    'school_class_id' => $class->id,
                    'arm_id' => $armId,
                ]));
            }
        });

        return $created;
    }

    private static function uniqueClassCode(string $baseCode, string $className, int $classId): string
    {
        $suffix = Str::upper(Str::slug($className, ''));
        $suffix = $suffix !== '' ? $suffix : 'CLASS'.$classId;
        $code = static::withClassSuffix($baseCode, $suffix);

        if (static::query()->where('code', $code)->exists()) {
            $code = static::withClassSuffix($baseCode, 'C'.$classId);
        }

        if (static::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'A unique code could not be generated for one of the selected classes. Please use a different subject code.']);
        }

        return $code;
    }

    private static function withClassSuffix(string $baseCode, string $suffix): string
    {
        $tail = '-'.$suffix;
        $prefix = rtrim(substr($baseCode, 0, 40 - strlen($tail)), '-');

        return $prefix.$tail;
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
        });
    }

    public function schoolClass() { return $this->belongsTo(SchoolClass::class); }
    public function arm() { return $this->belongsTo(Arm::class); }
    public function teacherAssignments() { return $this->hasMany(TeacherAssignment::class); }
    public function results() { return $this->hasMany(Result::class); }
}
