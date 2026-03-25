<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GreenApi\GreenApiClient;
use App\Services\GreenApi\GreenApiMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GreenApiWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        GreenApiClient $client,
        GreenApiMessageService $messageService,
    ): JsonResponse {
        $expectedToken = $client->webhookToken();

        if ($expectedToken !== null && ! hash_equals($expectedToken, (string) $request->header('Authorization', ''))) {
            abort(401);
        }

        $messageService->handleWebhook($request->all());

        return response()->json(status: 204);
    }
}
