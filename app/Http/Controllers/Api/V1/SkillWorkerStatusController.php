<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SkillWorkerStatusController extends Controller
{
    public function finder(Request $request): Response
    {
        return $this->update($request, 'finder');
    }

    public function controller(Request $request): Response
    {
        return $this->update($request, 'controller');
    }

    private function update(Request $request, string $worker): Response
    {
        $data = $request->validate([
            'run_id' => ['required', 'uuid'],
            'active' => ['required', 'boolean'],
        ]);
        $key = 'skill-worker-status:'.$worker;

        Cache::lock($key.':lock', 5)->get(function () use ($key, $data): void {
            if ($data['active']) {
                Cache::put($key, $data['run_id'], now()->addSeconds(90));
            } elseif (Cache::get($key) === $data['run_id']) {
                Cache::forget($key);
            }
        });

        return response()->noContent();
    }
}
