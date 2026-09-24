<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Archivo extends Model
{
    protected $fillable = ['categoria', 'nombre', 'ruta', 'mime', 'tamano', 'user_id'];

    public const CATEGORIAS = [
        'foto' => 'Foto',
        'manual' => 'Manual',
        'diagrama' => 'Diagrama',
        'evidencia' => 'Evidencia',
        'documento' => 'Documento',
    ];

    public function adjuntable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->ruta);
    }

    public function esImagen(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
