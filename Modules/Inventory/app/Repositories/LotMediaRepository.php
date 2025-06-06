<?php

namespace Modules\Inventory\Repositories;

use Modules\Inventory\Models\LotMedia;

class LotMediaRepository
{
    public function handle() {}

    public function create(array $data): LotMedia
    {
        if (!isset($data['position'])) {
            $max = LotMedia::where('lot_id', $data['lot_id'])->max('position');
            $data['position'] = $max ? $max + 1 : 1;
        }
        return LotMedia::create($data);
    }

    public function update(LotMedia $media, array $data): LotMedia
    {
        $media->update($data);
        return $media;
    }

    public function delete(LotMedia $media): void
    {
        $media->delete();
    }
}
