<?php

namespace App\Http\Controllers\Admin\Shipping;

use App\Http\Controllers\Controller;
use App\Services\Orders\OrderTraceSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderTraceController extends Controller
{
    public function __invoke(Request $request, OrderTraceSearch $search): Response
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) > 64) {
            $term = mb_substr($term, 0, 64);
        }

        $result = $term === ''
            ? ['orders' => [], 'events' => []]
            : $search->search($term);

        return Inertia::render('Admin/Shipping/OrderTraces', [
            'q' => $term,
            'orders' => $result['orders'],
            'events' => $result['events'],
        ]);
    }
}
