<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'additional_emails'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $visible = [
        'id',
        'name',
        'email',
        'role',
        'additional_emails',
        'created_at'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'additional_emails' => 'array',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, "client_id");
    }

    public function assignedProjects(): HasMany
    {
        return $this->hasMany(Project::class, "editor_id");
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    // Role check helpers
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEditor(): bool
    {
        return $this->role === 'editor';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }



    public function getAllEmails(): array
    {
        $emails = [];

        // ✅ Add primary email if valid
        if (!empty($this->email) && filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $this->email;
        }

        // ✅ Handle additional_emails if present
        if (!empty($this->additional_emails)) {
            if (is_array($this->additional_emails)) {
                $extraEmails = $this->additional_emails;
            } else {
                $extraEmails = array_map('trim', explode(',', $this->additional_emails));
            }

            foreach ($extraEmails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
        }

        return $emails;
    }


    
}
