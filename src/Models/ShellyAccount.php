<?php

namespace DaedalosLabs\FilamentShelly\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shelly Cloud credentials (Shelly app → User settings → Authorization cloud key).
 *
 * @property ?string $server_uri
 * @property ?string $auth_key
 */
class ShellyAccount extends Model
{
    protected $table = 'shelly_accounts';

    protected $fillable = ['server_uri', 'auth_key'];

    protected $hidden = ['auth_key'];

    protected function casts(): array
    {
        return ['auth_key' => 'encrypted'];
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(ShellySensor::class)->orderBy('sort');
    }

    public function isConfigured(): bool
    {
        // An undecryptable key (e.g. saved under another APP_KEY) counts as not configured.
        return filled($this->server_uri) && filled(rescue(fn () => $this->auth_key, report: false));
    }
}
