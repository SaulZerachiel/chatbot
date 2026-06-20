<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model {
    // Champs qu'on peut remplir en masse (sécurité Laravel)
    protected $fillable = ['user_id', 'title', 'model'];
    // Une conversation appartient à un utilisateur
    public function user(): BelongsTo {
    return $this->belongsTo(User::class);
    }
    // Une conversation a plusieurs messages (triés par date)
    public function messages(): HasMany {
    return $this->hasMany(Message::class)->orderBy('created_at');
    }
}
