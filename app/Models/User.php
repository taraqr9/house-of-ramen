<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Lab404\Impersonate\Models\Impersonate;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string|null $avatar_path
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property bool $is_active
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 */
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, HasUserStamps, Impersonate, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'avatar_path',
        'email',
        'email_verified_at',
        'password',
        'password_setup_token',
        'password_setup_expires_at',
        'password_changed_at',
        'remarks',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'password_setup_expires_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => StatusEnum::class,
        ];
    }

    public static function getActiveUsers(): Collection
    {
        return User::where('is_active', true)->orderBy('name')->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(strtolower(class_basename($this)))
            ->logAll()
            ->logExcept([
                'password',
                'remember_token',
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                return class_basename($this)." {$eventName}<br>".
                    '<strong>Table:</strong> '.$this->getTable();
            });
    }
}
