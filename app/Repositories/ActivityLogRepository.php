<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogRepository
{
    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)
            ->with('actor:id,name,email,role')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?ActivityLog
    {
        return ActivityLog::query()
            ->with('actor:id,name,email,role,org_level')
            ->find($id);
    }

    /** @param  array<string, mixed>  $filters */
    private function query(array $filters): Builder
    {
        $query = ActivityLog::query();

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('subject_label', 'like', $term)
                    ->orWhere('action', 'like', $term)
                    ->orWhere('properties', 'like', $term)
                    ->orWhereHas('actor', fn (Builder $actor) => $actor
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term));
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
