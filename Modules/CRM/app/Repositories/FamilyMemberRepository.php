<?php

namespace Modules\CRM\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CRM\Models\FamilyMember;

class FamilyMemberRepository
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return FamilyMember::with('client')
            ->when($filters['client_id'] ?? null, fn($q, $id) => $q->where('client_id', $id))
            ->orderBy('family_member_id')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data): FamilyMember
    {
        return FamilyMember::create($data)->load('client');
    }

    public function find(int $id): FamilyMember
    {
        return FamilyMember::with('client')->findOrFail($id);
    }

    public function update(FamilyMember $member, array $data): FamilyMember
    {
        $member->update($data);
        return $member->load('client');
    }

    public function delete(FamilyMember $member): void
    {
        $member->delete();
    }

    public function belongsToClient(FamilyMember $member, int $clientId): bool
    {
        return $member->client_id === $clientId;
    }
}
