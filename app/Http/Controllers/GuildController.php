<?php

namespace App\Http\Controllers;

use App\{AuditLog, Guild, Member, Permission, Role, User};
use Auth;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use RestCord\DiscordClient;

class GuildController extends Controller
{
    const ADMIN_PERMISSIONS = 0x8;
    const MANAGEMENT_PERMISSIONS = 0x20;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'seeUser']);
    }

    /**
     * Default page for landing on a guild
     *
     * @return \Illuminate\Http\Response
     */
    public function home($guildId, $guildSlug)
    {
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        request()->session()->reflash();
        return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
    }

    /**
     * Find a guild by slug.
     *
     * @param int $guildSlug The slug of the guild to find.
     *
     * @return \Illuminate\Http\Response
     */
    public function find($guildSlug)
    {
        $guild = Guild::select(['id', 'slug'])->where('slug', $guildSlug)->first();

        if (!$guild) {
            request()->session()->flash('status', 'Could not find guild.');
            return redirect()->route('home');
        }

        return redirect()->route('guild.home', ['guildId' => $guild->id, 'guildSlug' => $guildSlug]);
    }

    /**
     * Export a guild's wishlist data
     *
     * @return \Illuminate\Http\Response
     */
    public function exportWishlist($guildId, $guildSlug)
    {
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        if ($guild->is_wishlist_locked && !$currentMember->hasPermission('loot.characters')) {
            request()->session()->flash('status', 'You don\'t have permissions to view wishlists.');
            return redirect()->route('guild.home', ['guildId' => $guild->id, 'guildSlug' => $guildSlug]);
        }

        $showOfficerNote = false;
        if ($currentMember->hasPermission('view.officer-notes') && !isStreamerMode()) {
            $showOfficerNote = true;
        }

        $header = [
                "raid_name",
                "character_name",
                "character_class",
                "character_inactive_at",
                "sort_order",
                "item_name",
                "item_id",
                "note",
                // "officer_note",
                "received_at",
                "import_id",
                "created_at",
                "updated_at",
                "item_note",
                "item_prio_note",
            ];

        $officerNote = ($showOfficerNote ? 'ci.officer_note' : 'NULL');
        $rows = DB::select(DB::raw(
                "SELECT
                    r.name AS 'raid_name',
                    c.name AS 'character_name',
                    c.class AS 'character_class',
                    c.inactive_at AS 'character_inactive_at',
                    ci.`order` AS 'sort_order',
                    i.name AS 'item_name',
                    ci.item_id AS 'item_id',
                    ci.note AS 'note',
                    -- {$officerNote} AS 'officer_note',
                    ci.received_at AS 'received_at',
                    ci.import_id AS 'import_id',
                    ci.created_at AS 'created_at',
                    ci.updated_at AS 'updated_at',
                    gi.note AS 'item_note',
                    gi.priority AS 'item_prio_note'
                FROM character_items ci
                    JOIN characters c ON c.id = ci.character_id
                    LEFT JOIN raids r ON r.id = c.raid_id
                    JOIN items i ON i.item_id = ci.item_id
                    LEFT JOIN guild_items gi ON gi.item_id = i.item_id AND gi.guild_id = c.guild_id
                WHERE ci.type = 'wishlist' AND c.guild_id = {$guild->id}
                ORDER BY r.name, c.name, ci.`order`;"));

        // output up to 5MB is kept in memory, if it becomes bigger it will automatically be written to a temporary file
        $csv = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+');

        fputcsv($csv, $header);
        foreach ($rows as $row) {
            // Convert from stdClass to array
            $row = get_object_vars($row);
            fputcsv($csv, $row);
        }
        rewind($csv);
        $csv = stream_get_contents($csv);

        return view('guild.export.generic', [
            'currentMember' => $currentMember,
            'guild'         => $guild,
            'csv'           => $csv,
        ]);
    }

    /**
     * Show the guild registration.
     *
     * @return \Illuminate\Http\Response
     */
    public function showRegister()
    {
        $user = request()->get('currentUser');

        $guildArray = [];

        // Fetch guilds the user can join that already exist on this website
        if ($user->discord_token) {
            $discord = new DiscordClient([
                'token' => $user->discord_token,
                'tokenType' => 'OAuth',
            ]);

            $guilds = $discord->user->getCurrentUserGuilds();

            if ($guilds) {
                foreach ($guilds as $guild) {
                    // only add guilds they have admin permissions for
                    if (($guild->permissions & self::ADMIN_PERMISSIONS) == self::ADMIN_PERMISSIONS) {
                        $guildArray[$guild->id] = [
                            'id'          => $guild->id,
                            'name'        => $guild->name,
                            'registered'  => false,
                            'permissions' => $guild->permissions,
                        ];
                    }
                }

                $existingGuilds = Guild::whereIn('discord_id', array_keys($guildArray))->get();

                // Flag guilds that are already registered
                foreach ($existingGuilds as $guild) {
                    if (isset($guildArray[$guild->discord_id])) {
                        $guildArray[$guild->discord_id]['registered'] = true;
                    }
                }
            }
        }

        return view('guild.register', ['guilds' => $guildArray]);
    }

    /**
     * Register a guild
     *
     * @return \Illuminate\Http\Response
     */
    public function register()
    {
        $validationRules =  [
            'name'              => 'string|max:36|unique:guilds,name',
            'discord_id_select' => 'nullable|string|max:255|unique:guilds,discord_id|required_without:discord_id',
            'discord_id'        => 'nullable|string|max:255|unique:guilds,discord_id|required_without:discord_id_select',
            'bot_added'         => 'numeric|gte:1',
        ];

        $validationMessages = [
        ];

        $this->validate(request(), $validationRules);

        $input = request()->all();
        $user = Auth::user();

        $discordId = null;

        if ($input['discord_id']) {
            $discordId = $input['discord_id'];
        } else if ($input['discord_id_select']) {
            $discordId = $input['discord_id_select'];
        }

        // Verify that the bot is on the server
        $discord = new DiscordClient(['token' => env('DISCORD_BOT_TOKEN')]);

        try {
            $discordMember = $discord->guild->getGuildMember(['guild.id' => (int)$discordId, 'user.id' => (int)$user->discord_id]);
        } catch (Exception $e) {
            $error = \Illuminate\Validation\ValidationException::withMessages([
               'permissions' => ["Unable to find you on that server, or the bot is missing. Make sure you have the correct Discord Server ID and the bot has been added."],
            ]);
            throw $error;
        }

        $discordGuild = $discord->guild->getGuild(['guild.id' => (int)$discordId]);

        $hasPermissions = false;

        $roles = $discord->guild->getGuildRoles(['guild.id' => (int)$discordId]);


        if ($discordMember->user->id == $discordGuild->owner_id) {
            // You own the server... come right in.
            $hasPermissions = true;
        } else {
            // Go through each of the user's roles, and check to see if any of them have admin or management permissions
            // We're only going to let the user register this server if they have one of those permissions
            foreach ($discordMember->roles as $role) {
                $discordPermissions = $roles[array_search($role, array_column($roles, 'id'))]->permissions;
                if (($discordPermissions & self::ADMIN_PERMISSIONS) == self::ADMIN_PERMISSIONS) { // if we want to allow management permissions: || ($permissions & self::MANAGEMENT_PERMISSIONS) == self::MANAGEMENT_PERMISSIONS
                    $hasPermissions = true;
                    break;
                }
            }
        }

        if (!$hasPermissions) {
            $error = \Illuminate\Validation\ValidationException::withMessages([
               'permissions' => ["We couldn't find admin permissions on your account for that server. Have someone with admin permissions register your guild."],
            ]);
            throw $error;
        }

        // Create the guild
        $guild = Guild::firstOrCreate(['name' => $input['name']],
            [
                'slug'       => slug($input['name']),
                'user_id'    => $user->id,
                'discord_id' => $discordId,
            ]);

        // Insert the roles associated with this Discord
        foreach ($roles as $role) {
            $role = Role::firstOrCreate(['discord_id' => $role->id],
                [
                    'name'                => $role->name,
                    'guild_id'            => $guild->id,
                    'slug'                => slug($role->name),
                    'description'         => null,
                    'color'               => $role->color ? $role->color : null,
                    'position'            => $role->position,
                    'discord_permissions' => $role->permissions,
                ]);
        }

        $member = Member::create($user, $discordMember, $guild);

        AuditLog::create([
            'description'     => $member->username . ' registered the guild',
            'member_id'       => $member->id,
            'guild_id'        => $guild->id,
        ]);

        // Redirect to guild settings page; prompting the user to finish setup
        request()->session()->flash('status', 'Successfully registered guild.');
        return redirect()->route('guild.settings', ['guildId' => $guild->id, 'guildSlug' => $guild->slug]);
    }

    /**
     * Show the guild change owner page.
     *
     * @return \Illuminate\Http\Response
     */
    public function owner($guildId, $guildSlug)
    {
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');
        $user          = request()->get('currentUser');

        if (!request()->get('isSuperAdmin')) {
            request()->session()->flash('status', 'You don\'t have permissions to change the guild owner. Only the current owner can do that');
            return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
        }

        $guild->load(['members']);

        $members = $guild->members()->with('user')->get();

        return view('guild.owner', [
            'currentMember' => $currentMember,
            'guild'         => $guild,
            'members'       => $members,
        ]);
    }

    /**
     * Submit the guild change owner page.
     *
     * @return \Illuminate\Http\Response
     */
    public function submitOwner($guildId, $guildSlug)
    {
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        if (!request()->get('isSuperAdmin')) {
            request()->session()->flash('status', 'You don\'t have permissions to change the guild owner. Only the current owner can do that.');
            return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
        }

        $validationRules =  [
            'member_id' => 'nullable|integer|exists:members,id',
        ];

        $this->validate(request(), $validationRules);

        $message = '';

        if (request()->input('member_id')) {

            $member = $guild->members()->where('id', request()->input('member_id'))->with('user')->firstOrFail();

            if ($member->user->id == $guild->user_id) {
                $message = 'unchanged';
            } else {
                $guild->update(['user_id' => $member->user->id]);

                AuditLog::create([
                    'description'     => $currentMember->username . ' changed the guild owner to ' . $member->username . ' (' . $member->user->discord_username . ').',
                    'member_id'       => $currentMember->id,
                    'guild_id'        => $guild->id,
                ]);

                $message = 'changed to ' . $member->username . ' (' . $member->user->discord_username . ').';
            }
        } else {
            $message = 'unchanged';
        }

        request()->session()->flash('status', 'Guild owner ' . $message . '.');
        return redirect()->route('guild.settings', ['guildId' => $guild->id, 'guildSlug' => $guild->slug]);
    }

    /**
     * Show the guild settings page.
     *
     * @return \Illuminate\Http\Response
     */
    public function settings($guildId, $guildSlug)
    {
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        if (!$currentMember->hasPermission('edit.guild')) {
            request()->session()->flash('status', 'You don\'t have permissions to view that page.');
            return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
        }

        $guild->load(['roles']);

        $owner = $guild->members()->where([
            ['user_id', $guild->user_id],
        ])->with('user')->first();

        return view('guild.settings', [
            'currentMember' => $currentMember,
            'guild'         => $guild,
            'owner'         => $owner,
            'permissions'   => Permission::all(),
        ]);
    }

    /**
     * Submit the guild settings page.
     *
     * @return \Illuminate\Http\Response
     */
    public function submitSettings($guildId, $guildSlug)
    {
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        if (!$currentMember->hasPermission('edit.guild')) {
            request()->session()->flash('status', 'You don\'t have permissions to edit that guild.');
            return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
        }

        $guild->load('roles');

        $validationRules =  [
            'name'                      => 'string|max:36|unique:guilds,name,' . $guild->id,
            'disabled_at'               => 'nullable|boolean',
            'is_prio_private'           => 'nullable|boolean',
            'is_received_locked'        => 'nullable|boolean',
            'is_wishlist_private'       => 'nullable|boolean',
            'is_wishlist_locked'        => 'nullable|boolean',
            'is_prio_autopurged'        => 'nullable|boolean',
            'is_wishlist_autopurged'    => 'nullable|boolean',
            'do_sort_items_by_instance' => 'nullable|boolean',
            'calendar_link'             => 'nullable|string|max:200',
            'message'                   => 'nullable|string|max:500',
            'show_message'              => 'nullable|boolean',
            'gm_role_id'                => 'nullable|integer|exists:roles,discord_id',
            'officer_role_id'           => 'nullable|integer|exists:roles,discord_id',
            'raid_leader_role_id'       => 'nullable|integer|exists:roles,discord_id',
            'member_roles.*'            => 'nullable|integer|exists:roles,discord_id',
        ];

        $this->validate(request(), $validationRules);

        $updateValues['name']                      = request()->input('name');
        $updateValues['slug']                      = slug(request()->input('name'));
        $updateValues['is_prio_private']           = request()->input('is_prio_private') == 1 ? 1 : 0;
        $updateValues['is_received_locked']        = request()->input('is_received_locked') == 1 ? 1 : 0;
        $updateValues['is_wishlist_private']       = request()->input('is_wishlist_private') == 1 ? 1 : 0;
        $updateValues['is_wishlist_locked']        = request()->input('is_wishlist_locked') == 1 ? 1 : 0;
        $updateValues['is_prio_autopurged']        = request()->input('is_prio_autopurged') == 1 ? 1 : 0;
        $updateValues['is_wishlist_autopurged']    = request()->input('is_wishlist_autopurged') == 1 ? 1 : 0;
        $updateValues['do_sort_items_by_instance'] = request()->input('do_sort_items_by_instance') == 1 ? 1 : 0;
        $updateValues['message']                   = request()->input('message');
        $updateValues['calendar_link']             = request()->input('calendar_link');
        $updateValues['member_role_ids']           = implode(",", array_filter(request()->input('member_roles')));

        $updateValues = $this->flushRoles($guild, $updateValues);

        $auditMessage = '';

        if ($updateValues['name'] != $guild->name) {
            $auditMessage .= ' (guild name changed to ' . $updateValues['name'] . ')';
        }

        // Guild disable/enable checks
        if (Auth::id() == $guild->user_id) {
            $isDisabled = request()->input('disabled_at') == 1 ? 1 : 0;
            if ($isDisabled != ($guild->disabled_at != null)) {
                if ($isDisabled) {
                    $updateValues['disabled_at'] = getDateTime();
                    $auditMessage .= ' (disabled guild)';
                } else {
                    $updateValues['disabled_at'] = null;
                    $auditMessage .= ' (enabled guild)';
                }
            }
        }

        if (!request()->input('show_message') && $guild->message) {
            $auditMessage .= ' (MOTD updated)';
            $updateValues['message'] = null;
        } else if ($updateValues['message'] != $guild->message) {
            $auditMessage .= ' (MOTD updated)';
        }

        if (array_key_exists('gm_role_id', $updateValues) && $updateValues['gm_role_id'] != $guild->gm_role_id) {
            $role = $guild->roles->where('discord_id', $updateValues['gm_role_id'])->first();
            $auditMessage .= ' (GM role changed to ' . ($role ? $role->name : 'none') . ')';
        }
        if (array_key_exists('officer_role_id', $updateValues) && $updateValues['officer_role_id'] != $guild->officer_role_id) {
            $role = $guild->roles->where('discord_id', $updateValues['officer_role_id'])->first();
            $auditMessage .= ' (Officer role changed to ' . ($role ? $role->name : 'none') . ')';
        }
        if (array_key_exists('raid_leader_role_id', $updateValues) && $updateValues['raid_leader_role_id'] != $guild->raid_leader_role_id) {
            $role = $guild->roles->where('discord_id', $updateValues['raid_leader_role_id'])->first();
            $auditMessage .= ' (Raid Leader role changed to ' . ($role ? $role->name : 'none') . ')';
        }

        if ($updateValues['member_role_ids'] != $guild->member_role_ids) {
            $memberRoles = $guild->roles->whereIn('discord_id', request()->input('member_roles'));
            $memberRoleMessage = '';
            foreach ($memberRoles as $memberRole) {
                $memberRoleMessage .= $memberRole->name .', ';
            }
            $auditMessage .= ' (whitelisted member roles changed to ' . ($memberRoleMessage ? trim($memberRoleMessage, ', ') : 'none') . ')';
        }

        $guild->update($updateValues);

        AuditLog::create([
            'description'     => $currentMember->username . ' modified guild settings' . $auditMessage,
            'member_id'       => $currentMember->id,
            'guild_id'        => $guild->id,
        ]);

        request()->session()->flash('status', 'Guild settings updated.');
        return redirect()->route('guild.settings', ['guildId' => $guild->id, 'guildSlug' => $guild->slug]);
    }

    /**
     * Wipes out ALL old role permissions and re-adds them.
     *
     * @param $guild        The guild object we're working on, with its roles relationship eager loaded.
     * @param $updateValues An array of the values the guild will be updated with. We'll add our values and pass this back.
     *
     * @return array An updated version of the $updateValues param
     */
    private function flushRoles($guild, $updateValues) {
        $permissions = Permission::all();

        // Flush out the old role permissions
        if ($guild->gm_role_id) {
            $role = $guild->roles->where('discord_id', $guild->gm_role_id)->first();
            if ($role) {
                $role->permissions()->detach();
            }
        }
        if ($guild->officer_role_id) {
            $role = $guild->roles->where('discord_id', $guild->officer_role_id)->first();
            if ($role) {
                $role->permissions()->detach();
            }
        }
        if ($guild->raid_leader_role_id) {
            $role = $guild->roles->where('discord_id', $guild->raid_leader_role_id)->first();
            if ($role) {
                $role->permissions()->detach();
            }
        }

        if (request()->input('gm_role_id')) {
            $role = $guild->roles->where('discord_id', request()->input('gm_role_id'))->first();
            if ($role) {
                // Attach the appropriate permissions to that role
                $rolePermissions = $permissions->whereIn('role_note', [Permission::GUILD_MASTER, Permission::OFFICER, Permission::RAID_LEADER]);
                $role->permissions()->syncWithoutDetaching($rolePermissions->keyBy('id')->keys()->toArray());
                $updateValues['gm_role_id'] = request()->input('gm_role_id');
            }
        } else {
            $updateValues['gm_role_id'] = null;
        }
        // Copy of the role code seen above
        if (request()->input('officer_role_id')) {
            $role = $guild->roles->where('discord_id', request()->input('officer_role_id'))->first();
            if ($role) {
                $rolePermissions = $permissions->whereIn('role_note', [Permission::OFFICER, Permission::RAID_LEADER]);
                $role->permissions()->syncWithoutDetaching($rolePermissions->keyBy('id')->keys()->toArray());
                $updateValues['officer_role_id'] = request()->input('officer_role_id');
            }
        } else {
            $updateValues['officer_role_id'] = null;
        }
        // Copy of the role code seen above
        if (request()->input('raid_leader_role_id')) {
            $role = $guild->roles->where('discord_id', request()->input('raid_leader_role_id'))->first();
            if ($role) {
                $rolePermissions = $permissions->whereIn('role_note', [Permission::RAID_LEADER]);
                $role->permissions()->syncWithoutDetaching($rolePermissions->keyBy('id')->keys()->toArray());
                $updateValues['raid_leader_role_id'] = request()->input('raid_leader_role_id');
            }
        } else {
            $updateValues['raid_leader_role_id'] = null;
        }

        return $updateValues;
    }
}
