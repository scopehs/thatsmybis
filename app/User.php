<?php

namespace App;

use App\{Guild, Member, Role};
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Kodeine\Acl\Traits\HasRole;
use RestCord\DiscordClient;

class User extends Authenticatable
{
    use Notifiable, HasRole;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username',
        // 'email',
        // 'email_verified_at',
        'discord_id',
        'discord_username',
        'discord_avatar',
        'banned_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // 'email',
        'discord_id',
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function guilds()
    {
        return $this->hasMany(Guild::class, 'user_id');
    }

    public function members() {
        return $this->hasMany(Member::class, 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    // TODO
    // Fetch the user's roles from Discord and sync them
    public function fetchAndSyncRoles() {
        $discord = new DiscordClient(['token' => env('DISCORD_BOT_TOKEN')]);
        $discordMember = $discord->guild->getGuildMember(['guild.id' => (int)env('GUILD_ID'), 'user.id' => (int)$this->discord_id]);
        $roles = Role::whereIn('discord_id', $discordMember->roles)->get()->keyBy('id')->keys()->toArray();
        $this->syncRoles($roles);
    }
}
