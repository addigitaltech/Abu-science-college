<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectClassAssignment extends Model
{
    protected $table = 'subject_class_assignments';

    protected $fillable = ['subject_id', 'school_class_id', 'arm_id'];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function arm()
    {
        return $this->belongsTo(Arm::class);
    }
}
