<?php

use App\Exceptions\DomainRuleException;
use App\Http\Middleware\AuthenticateWithJwt;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ResolveHospital;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveHospital::class,
            // No «jwt.auth»: tymon/jwt-auth registra ese alias con su propio middleware y lo pisaría.
            'sesion' => AuthenticateWithJwt::class,
            'permiso' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Una regla de negocio rechazada es una respuesta normal (422/409), no un error del sistema: no va al log.
        $exceptions->dontReport(DomainRuleException::class);
        // Todo /api responde JSON (también 404 y 405), sin trazas ni detalles de la base.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        // E-07: una ruta de API inexistente da 404 y un método equivocado 405, siempre en JSON.
        // abort(404, 'Paciente no encontrado.') conserva su mensaje; una ruta inexistente da el genérico.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => $e->getMessage() !== '' && ! str_starts_with($e->getMessage(), 'The route') ? $e->getMessage() : 'Recurso no encontrado.'], 404)
            : null);
        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Método no permitido para este recurso.'], 405) : null);
    })->create();
