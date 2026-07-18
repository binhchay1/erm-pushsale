<?php

namespace App\Http\Controllers\Admin\Sales;

use Illuminate\Http\Request;

class WorkspaceController extends \App\Http\Controllers\Sales\OperationController
{
    protected function workspaceRouteUrl(Request $request): string
    {
        return '/admin/sales/workspace';
    }

    protected function workspaceActionBaseUrl(Request $request): string
    {
        return '/admin/sales';
    }

    protected function workspaceManualUrl(Request $request): string
    {
        return '/admin/sales/leads/manual';
    }
}
