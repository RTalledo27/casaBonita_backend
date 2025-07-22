<?php

namespace Modules\HumanResources\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResources\Models\Team;

class TeamRepository
{
    protected Team $model;

    public function __construct(Team $model)
    {
        $this->model = $model;
    }

    public function getAll(): Collection
    {
        return $this->model->with(['leader'])->get();
    }

    public function find(int $id): ?Team
    {
        return $this->model->with(['leader', 'employees'])->find($id);
    }

    public function create(array $data): Team
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?Team
    {
        $team = $this->find($id);
        if ($team) {
            $team->update($data);
            return $team->fresh(['leader', 'employees']);
        }
        return null;
    }

    public function delete(int $id): bool
    {
        $team = $this->find($id);
        if ($team) {
            return $team->delete();
        }
        return false;
    }

    public function getActiveTeams(): Collection
    {
        return $this->model->where('status', 'active')->with(['leader'])->get();
    }

    public function getTeamMembers(int $teamId): Collection
    {
        $team = $this->find($teamId);
        return $team ? $team->employees : collect();
    }

    public function assignLeader(int $teamId, int $leaderId): ?Team
    {
        $team = $this->find($teamId);
        if ($team) {
            $team->update(['team_leader_id' => $leaderId]);
            return $team->fresh(['leader', 'employees']);
        }
        return null;
    }

    public function toggleStatus(int $id): ?Team
    {
        $team = $this->find($id);
        if ($team) {
            $newStatus = $team->status === 'active' ? 'inactive' : 'active';
            $team->update(['status' => $newStatus]);
            return $team->fresh(['leader', 'employees']);
        }
        return null;
    }
}