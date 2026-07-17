<?php

namespace App\Http\Controllers\Testing;

use App\Http\Controllers\Controller;
use App\Services\Testing\StagingTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StagingTestController extends Controller
{
    public function __construct(private readonly StagingTestService $service) {}

    public function health(Request $request): JsonResponse
    {
        $this->guard($request);

        return response()->json($this->service->health());
    }

    public function pages(Request $request): JsonResponse
    {
        $this->guard($request);

        $urls = null;
        if ($request->filled('urls')) {
            $urls = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('urls')))));
        }

        return response()->json($this->service->scanPages($urls));
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $this->guard($request, requireArtisan: true);

        return response()->json($this->service->bootstrapDemo(
            reset: $request->boolean('reset'),
            campaigns: (int) $request->integer('campaigns', 2),
            perCampaign: (int) $request->integer('per_campaign', 8),
        ));
    }

    public function flow(Request $request): JsonResponse
    {
        $this->guard($request, requireArtisan: true);

        $phone = $request->filled('phone') ? (string) $request->query('phone') : null;

        return response()->json($this->service->fullFlow($phone));
    }


    public function landingFlow(Request $request): JsonResponse
    {
        $this->guard($request, requireArtisan: true);

        $phone = $request->filled('phone') ? (string) $request->query('phone') : null;

        return response()->json($this->service->landingConnectionFlow($phone));
    }

    public function audit(Request $request): JsonResponse
    {
        $this->guard($request, requireArtisan: true);

        return response()->json($this->service->audit());
    }

    private function guard(Request $request, bool $requireArtisan = false): void
    {
        if (! (bool) config('staging_test.enabled', false)) {
            throw new NotFoundHttpException();
        }

        $allowedHosts = array_values(array_filter(array_map(
            static fn (string $host) => trim(mb_strtolower($host)),
            (array) config('staging_test.allowed_hosts', []),
        )));

        if ($allowedHosts !== [] && ! in_array(mb_strtolower($request->getHost()), $allowedHosts, true)) {
            throw new NotFoundHttpException();
        }

        $secret = trim((string) config('staging_test.secret', ''));
        $given = trim((string) $request->query('secret', ''));

        if ($secret === '' || $given === '' || ! hash_equals($secret, $given)) {
            throw new NotFoundHttpException();
        }

        if ($requireArtisan && ! (bool) config('staging_test.allow_artisan', false)) {
            abort(403, 'Staging artisan actions are disabled.');
        }
    }
}
