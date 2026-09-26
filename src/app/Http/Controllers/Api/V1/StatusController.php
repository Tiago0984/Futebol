<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

class StatusController extends Controller
{
    // GET /api/v1/status - confirma que a API está no ar
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'aplicacao' => config('app.name'),
                'api'       => 'v1',
                'status'    => 'online',
            ],
        ]);
    }
}
