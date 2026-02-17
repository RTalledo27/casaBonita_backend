<?php

namespace Modules\HumanResources\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResources\Models\BonusGoal;

class BonusGoalRepository
{
    protected BonusGoal $model;

    public function __construct( BonusGoal $model)
    {
        $this->model = $model;
    }

    public function getAll() : Collection {
        return $this->model->with(['bonusType', 'team', 'office'])->get();
    }

    public function find(int $id) : ?BonusGoal {
        return $this->model->with(['bonusType', 'team', 'office'])->find($id);
    }

    public function create(array $data): BonusGoal
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?BonusGoal
    {
        $bonusGoal = $this->find($id);
        if ($bonusGoal) {
            $bonusGoal->update($data);
        }
        return $bonusGoal;
    }

    public function delete(int $id): bool
    {
        $bonusGoal = $this->find($id);
        if ($bonusGoal) {
            return $bonusGoal->delete();
        }
        return false;
    }
}
