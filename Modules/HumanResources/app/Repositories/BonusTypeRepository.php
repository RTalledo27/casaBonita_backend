<?php

namespace Modules\HumanResources\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResources\Models\BonusType;

class BonusTypeRepository
{

    protected BonusType $model;

    public function __construct(BonusType $model)
    {
        $this->model = $model;
    }

    public function getAll():Collection
    {
        return $this->model->all();
    }

    public function getActive():Collection
    {
        return $this->model->where('is_active', true)->get();
    }

    public function find(int $id): ?BonusType{
        return $this->model->find($id);
    }

    public function create(array $data):BonusType{
        return $this->model->create($data);
    }


    public function update(int $id, array $data):?BonusType{
        $bonusType = $this->find($id);
        if ($bonusType) {
            $bonusType->update($data);
        }
        return $bonusType;
    }

    public function delete(int $id):bool{
        $bonusType = $this->find($id);
        if ($bonusType) {
            return $bonusType->delete();
        }
        return false;
    }
}
