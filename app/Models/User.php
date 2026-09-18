<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /** المراحل الدراسية المسموح بها في النظام. */
    public const STAGES = ['الابتدائي', 'المتوسط', 'الثانوي', 'الجامعة'];

    /** نطاقات صلاحية المساعد. */
    public const SCOPES = ['print', 'manage'];

    /** دور المشاهد المقيّد (عرض فقط ضمن مصفوفة مادة+صف). */
    public const ROLE_RESTRICTED_VIEWER = 'restricted_viewer';

    protected $fillable = [
        'name',
        'phone',
        'password',
        'role',
        'stages',
        'allowed_teachers',
        'scope',
        'teacher_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password'          => 'hashed',   // تشفير تلقائي عند الحفظ (Laravel 10+)
        'stages'            => 'array',    // تُخزَّن كـ JSON وتُقرأ كمصفوفة
        'allowed_teachers'  => 'array',
        'is_active'         => 'boolean',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /** الصفوف الدراسية التي أنشأها المدرس. */
    public function classes()
    {
        return $this->hasMany(SchoolClass::class);
    }

    /** صفوف مصفوفة صلاحيات المشاهد المقيّد (مواد محددة). */
    public function viewerPermissions()
    {
        return $this->hasMany(ViewerPermission::class);
    }

    /** المدرّس الوحيد المربوط به المشاهد المقيّد (كل محتواه محصور فيه). */
    public function viewerTeacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin_press';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isRestrictedViewer(): bool
    {
        return $this->role === self::ROLE_RESTRICTED_VIEWER;
    }
}
