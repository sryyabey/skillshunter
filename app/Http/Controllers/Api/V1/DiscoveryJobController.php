<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SkillDiscoveryJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiscoveryJobController extends Controller
{
    private const MAX_ATTEMPTS = 2;

    public function claim(Request $request): JsonResponse
    {
        $sourceInventory = $request->boolean('source_inventory');

        return DB::transaction(function () use ($sourceInventory): JsonResponse {
            SkillDiscoveryJob::where('status', 'queued')->where('attempts', '>=', self::MAX_ATTEMPTS)
                ->update(['status' => 'failed', 'available_at' => null]);

            SkillDiscoveryJob::where('status', 'running')->where('lease_until', '<=', now())
                ->where('attempts', '>=', self::MAX_ATTEMPTS)
                ->update(['status' => 'failed', 'lease_token' => null, 'lease_until' => null, 'error' => 'Worker lease expired.']);

            $job = SkillDiscoveryJob::where('attempts', '<', self::MAX_ATTEMPTS)
                ->when($sourceInventory, fn (Builder $query) => $query->whereNotNull('source_file_id'),
                    fn (Builder $query) => $query->whereNull('source_file_id'))
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $queued) => $queued->where('status', 'queued')
                        ->where(fn (Builder $available) => $available->whereNull('available_at')->orWhere('available_at', '<=', now())))
                    ->orWhere(fn (Builder $running) => $running->where('status', 'running')->where('lease_until', '<=', now())))
                ->orderBy('id')->lockForUpdate()->first();

            if (! $job) {
                return response()->json(['data' => null]);
            }

            $job->forceFill([
                'status' => 'running', 'attempts' => $job->attempts + 1,
                'lease_token' => Str::random(64), 'lease_until' => now()->addMinutes(40),
            ])->save();

            return response()->json(['data' => [
                'id' => $job->id, 'topic' => $job->topic, 'category_slug' => $job->category->slug,
                'result_limit' => $job->result_limit, 'lease_token' => $job->lease_token,
                'results' => $job->results ?? [],
                'source' => $job->source_snapshot,
            ]]);
        }, attempts: 3);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'lease_token' => ['required', 'string', 'size:64'],
            'status' => ['required', 'in:running,completed,failed'],
            'results' => ['present', 'array', 'list', 'max:3'],
            'results.*' => ['array:source_url,slug,status,warnings'],
            'results.*.source_url' => ['required', 'url:https', 'max:2048'],
            'results.*.slug' => ['required', 'string', 'max:160'],
            'results.*.status' => ['required', 'in:created,existing,published'],
            'results.*.warnings' => ['present', 'array', 'list', 'max:20'],
            'results.*.warnings.*' => ['string', 'max:500'],
            'error' => ['required_if:status,failed', 'nullable', 'string', 'max:1000'],
            'retryable' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($id, $data): JsonResponse {
            $job = SkillDiscoveryJob::lockForUpdate()->findOrFail($id);
            abort_unless($job->status === 'running' && $job->lease_until?->isFuture()
                && hash_equals((string) $job->lease_token, $data['lease_token']), 409, 'Worker lease expired.');

            $status = $data['status'];
            $retry = $status === 'failed' && ($data['retryable'] ?? false) && $job->attempts < self::MAX_ATTEMPTS;
            $job->forceFill([
                'status' => $retry ? 'queued' : $status,
                'results' => $data['results'],
                'error' => $data['error'] ?? null,
                'available_at' => $retry ? now()->addMinutes(10) : null,
                'lease_token' => $status === 'running' ? $job->lease_token : null,
                'lease_until' => $status === 'running' ? now()->addMinutes(40) : null,
            ])->save();

            return response()->json(['data' => ['status' => $job->status]]);
        }, attempts: 3);
    }
}
