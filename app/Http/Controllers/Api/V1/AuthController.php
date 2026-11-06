<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SwitchHospitalRequest;
use App\Models\Central\Hospital;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditTrail;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth, private readonly AuditTrail $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json($this->auth->login(
            $request->attributes->get('hospital'), $request->string('email'), (string) $request->input('password'), $request->ip(),
        ));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->auth->session($request->user(), $request->attributes->get('hospital')));
    }

    /** Hospitales donde el usuario tiene un rol vigente: alimenta el selector de la barra superior. */
    public function hospitals(Request $request): JsonResponse
    {
        $ids = $request->user()->memberships()->current()->pluck('hospital_id')->unique();

        return response()->json([
            'data' => Hospital::query()->active()->whereIn('hospital_id', $ids)->orderBy('name')
                ->get(['hospital_id', 'code', 'name'])
                ->map(fn (Hospital $h) => ['id' => $h->hospital_id, 'code' => $h->code, 'name' => $h->name]),
        ]);
    }

    public function switchHospital(SwitchHospitalRequest $request, PermissionResolver $permissions): JsonResponse
    {
        $user = $request->user();
        $target = Hospital::query()->active()->find($request->input('hospital_id'));

        if ($target === null || $permissions->rolesFor($user->user_id, $target->hospital_id) === []) {
            throw new DomainRuleException('SIN_ACCESO_AL_HOSPITAL', 'No tiene un rol vigente en ese hospital.', 403);
        }

        JWTAuth::invalidate(JWTAuth::getToken());
        $this->audit->record('autenticacion', 'CAMBIO_HOSPITAL', 'exito', $target->hospital_id, $user->user_id, null,
            ['desde' => $request->attributes->get('hospital')->hospital_id], $request->ip());

        return response()->json($this->auth->issue($user, $target));
    }

    public function refresh(Request $request): JsonResponse
    {
        $token = JWTAuth::refresh(JWTAuth::getToken());

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        $this->audit->record('autenticacion', 'CIERRE_SESION', 'exito', $request->attributes->get('hospital')->hospital_id,
            $request->user()->user_id, null, [], $request->ip());

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
