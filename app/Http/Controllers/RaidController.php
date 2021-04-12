<?php

namespace App\Http\Controllers;

use App\{AuditLog, Guild, Raid, RaidGroup};
use Illuminate\Http\Request;
use Kodeine\Acl\Models\Eloquent\Permission;

class RaidController extends Controller
{
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
     * Show a raid for editing
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($guildId, $guildSlug, $id = null) {
        // TODO: Copied from raidgroups
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        if (!$currentMember->hasPermission('edit.raids')) {
            request()->session()->flash('status', 'You don\'t have permissions to view that page.');
            return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
        }

        $guild->load([
            'allRaidGroups' => function ($query) use ($id) {
                return $query->where('id', $id);
            },
            'allRaidGroups.role']);

        $raidGroup = null;

        if ($id) {
            $raidGroup = $guild->allRaidGroups->where('id', $id)->first();

            if (!$raidGroup) {
                abort(404, 'Raid Group not found.');
            }
        }

        return view('guild.raidGroups.edit', [
            'currentMember' => $currentMember,
            'guild'         => $guild,
            'raidGroup'     => $raidGroup,
        ]);
    }

    /**
     * Create a raid
     * @return
     */
    public function create($guildId, $guildSlug) {
        // TODO: Copied from raidgroups
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        if (!$currentMember->hasPermission('create.raids')) {
            request()->session()->flash('status', 'You don\'t have permissions to create Raid Groups.');
            return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
        }

        $guild->load(['allRaidGroups', 'roles']);

        $validationRules = [
            'name'    => 'string|max:255',
            'role_id' => 'nullable|integer|exists:roles,id',
        ];

        $validationMessages = [];

        $this->validate(request(), $validationRules, $validationMessages);

        if ($guild->raidGroups->contains('name', request()->input('name'))) {
            abort(403, 'Name already exists.');
        }

        $role = null;

        if (request()->input('role_id')) {
            $role = $guild->roles->where('id', request()->input('role_id'));
            if (!$role) {
                abort(404, 'Role not found.');
            }
        }

        $createValues = [];

        $createValues['name']     = request()->input('name');
        $createValues['slug']     = slug(request()->input('name'));
        $createValues['role_id']  = request()->input('role_id');
        $createValues['guild_id'] = $guild->id;

        $raidGroup = RaidGroup::create($createValues);

        AuditLog::create([
            'description'   => $currentMember->username . ' created a Raid Group',
            'member_id'     => $currentMember->id,
            'guild_id'      => $guild->id,
            'raid_group_id' => $raidGroup->id,
        ]);

        request()->session()->flash('status', 'Successfully created Raid Group.');
        return redirect()->route('guild.raidGroups', ['guildId' => $guild->id, 'guildSlug' => $guild->slug]);
    }

    /**
     * Show the raids list page.
     *
     * @return \Illuminate\Http\Response
     */
    public function list($guildId, $guildSlug) {
        // TODO: Copied from raidgroups
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        if (!$currentMember->hasPermission('view.raids')) {
            request()->session()->flash('status', 'You don\'t have permissions to view that page.');
            return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
        }

        $guild->load(['allRaidGroups', 'allRaidGroups.role']);

        return view('guild.raidGroups.list', [
            'currentMember' => $currentMember,
            'guild'         => $guild,
        ]);
    }

    /**
     * Show a raid
     *
     * @return \Illuminate\Http\Response
     */
    public function show($guildId, $guildSlug, $id = null) {
        // TODO: Copied from raidgroups EDIT function
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        if (!$currentMember->hasPermission('edit.raids')) {
            request()->session()->flash('status', 'You don\'t have permissions to view that page.');
            return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
        }

        $guild->load([
            'allRaidGroups' => function ($query) use ($id) {
                return $query->where('id', $id);
            },
            'allRaidGroups.role']);

        $raidGroup = null;

        if ($id) {
            $raidGroup = $guild->allRaidGroups->where('id', $id)->first();

            if (!$raidGroup) {
                abort(404, 'Raid Group not found.');
            }
        }

        return view('guild.raidGroups.edit', [
            'currentMember' => $currentMember,
            'guild'         => $guild,
            'raidGroup'     => $raidGroup,
        ]);
    }

    /**
     * Update a raid
     * @return
     */
    public function update($guildId, $guildSlug) {
        // TODO: Copied from raidgroups
        $guild         = request()->get('guild');
        $currentMember = request()->get('currentMember');

        if (!$currentMember->hasPermission('edit.raids')) {
            request()->session()->flash('status', 'You don\'t have permissions to edit Raid Groups.');
            return redirect()->route('member.show', ['guildId' => $guild->id, 'guildSlug' => $guild->slug, 'memberId' => $currentMember->id, 'usernameSlug' => $currentMember->slug]);
        }

        $validationRules =  [
            'id'      => 'required|integer|exists:raid_groups,id',
            'name'    => 'string|max:255',
            'role_id' => 'nullable|integer|exists:roles,id',
        ];

        $this->validate(request(), $validationRules);

        $id = request()->input('id');

        $guild->load([
            'allRaidGroups' => function ($query) use ($id) {
                return $query->where('id', $id);
            },
        ]);

        $raidGroup = $guild->allRaidGroups->where('id', $id)->first();
        if (!$raidGroup) {
            abort(404, 'Raid Group not found.');
        }

        $role = null;
        if (request()->input('role_id')) {
            $role = $guild->roles->where('id', request()->input('role_id'));
            if (!$role) {
                abort(404, 'Role not found.');
            }
        }

        $updateValues = [];

        $updateValues['name']    = request()->input('name');
        $updateValues['slug']    = slug(request()->input('name'));
        $updateValues['role_id'] = request()->input('role_id');

        $auditMessage = '';

        if ($updateValues['name'] != $raidGroup->name) {
            $auditMessage .= ' (renamed to ' . $updateValues['name'] . ')';
        }

        $raidGroup->update($updateValues);

        AuditLog::create([
            'description'   => $currentMember->username . ' updated a Raid Group' . $auditMessage,
            'member_id'     => $currentMember->id,
            'guild_id'      => $guild->id,
            'raid_group_id' => $raidGroup->id,
        ]);

        request()->session()->flash('status', 'Successfully updated ' . $raidGroup->name . '.');
        return redirect()->route('guild.raidGroups', ['guildId' => $guild->id, 'guildSlug' => $guild->slug]);
    }
}
