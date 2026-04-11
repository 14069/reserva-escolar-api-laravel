<?php

use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiRequest = static function (Request $request): bool {
            $route = $request->route();
            $middleware = $route ? $route->gatherMiddleware() : [];

            return in_array('api', $middleware, true) || $request->expectsJson();
        };

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $isApiRequest($request)
        );

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error(
                'Dados inválidos.',
                422,
                'VALIDATION_ERROR',
                $exception->errors()
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error(
                'Recurso não encontrado.',
                404,
                'ROUTE_NOT_FOUND'
            );
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error(
                'Método não permitido.',
                405,
                'METHOD_NOT_ALLOWED'
            );
        });
    })->create();
