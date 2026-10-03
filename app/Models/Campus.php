<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'city', 'state', 'country', 'logo_path', 'status'];

    /**
     * Logo de la sede como data URI para los PDF (DomPDF no lee URLs del disco público).
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        return 'data:'.($disk->mimeType($this->logo_path) ?: 'image/png').';base64,'.base64_encode($disk->get($this->logo_path));
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
