<?php

namespace Modules\Inventory\Repositories;

use Modules\Inventory\Models\StreetType;

class StreetTypeRepository
{
    public function handle() {}

    public function all()
    {
        return StreetType::all();
    }

    public function create(array $data): StreetType
    {
        return StreetType::create($data);
    }

    public function update(StreetType $streetType, array $data): StreetType
    {
        $streetType->update($data);
        return $streetType;
    }

    public function delete(StreetType $streetType): void
    {
        if ($streetType->lots()->exists()) {
            throw new \RuntimeException('Street type in use');
        }
        $streetType->delete();
    }
}
