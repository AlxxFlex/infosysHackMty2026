<?php

use App\Exceptions\InvalidPlanExecution;
use App\Exceptions\InvalidSimulationEvent;
use App\Exceptions\InvalidSimulationTick;
use App\Exceptions\InvalidSimulationTransition;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API uses Laravel's default middleware stack and remains unauthenticated for local demos.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'The given data was invalid.', 'errors' => $e->errors(), 'code' => 'VALIDATION_ERROR'], 422);
            }
        });
        $exceptions->render(function (InvalidSimulationTransition|InvalidPlanExecution $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage(), 'errors' => [], 'code' => $e instanceof InvalidPlanExecution ? 'PLAN_EXECUTION_CONFLICT' : 'SIMULATION_CONFLICT'], 409);
            }
        });
        $exceptions->render(function (InvalidSimulationTick $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage(), 'errors' => [], 'code' => 'INVALID_SIMULATION_TICK'], 422);
            }
        });
        $exceptions->render(function (InvalidSimulationEvent $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage(), 'errors' => [], 'code' => 'INVALID_SIMULATION_EVENT'], 422);
            }
        });
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.', 'errors' => [], 'code' => 'NOT_FOUND'], 404);
            }
        });
        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage(), 'errors' => [], 'code' => 'INVALID_PLAN'], 422);
            }
        });
    })->create();
