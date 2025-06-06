<?php

namespace Modules\Inventory\Repositories;

use Modules\Inventory\Models\Manzana;

class ManzanaRepository
{
    public function handle() {}


    public function all()
    {
        return Manzana::all();
    }

    public function create(array $data): Manzana
    {
        return Manzana::create($data);
    }

    public function update(Manzana $manzana, array $data): Manzana
    {
        $manzana->update($data);
        return $manzana;
    }

    public function delete(Manzana $manzana): void
    {
        if ($manzana->lots()->exists()) {
            throw new \RuntimeException('Manzana in use');
        }
        $manzana->delete();
    }
}

