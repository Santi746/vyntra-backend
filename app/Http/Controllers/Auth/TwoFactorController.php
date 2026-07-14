<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Auth\EnableTwoFactorRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\AuthResource;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use PragmaRX\Google2FA\Google2FA;

/**
 * Controlador de autenticación de dos factores (2FA TOTP).
 *
 * Implementa el flujo completo:
 *   1. Enable: generar secret + QR
 *   2. Confirm: verificar primer código + generar backup codes
 *   3. Verify: verificar código durante login (token pendiente)
 *   4. Disable: desactivar 2FA
 *
 * Basado en TOTP (RFC 6238). Compatible con Google Authenticator,
 * Authy, 1Password, y cualquier app que soporte TOTP.
 */
class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * PASO 1: Iniciar activación de 2FA.
     *
     * Requiere: password actual (por seguridad).
     * Genera:
     *  - Un secret TOTP (guardado en two_factor_secret)
     *  - Un QR code (para escanear con Google Authenticator)
     *  - La URL manual (otpauth://) para copiar/pegar
     *
     * NO marca two_factor_enabled = true todavía.
     * El usuario debe CONFIRMAR con un primer código TOTP.
     */
    public function enable(EnableTwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Generar secret TOTP y guardarlo (aún no activado)
        $secret = $this->google2fa->generateSecretKey(32);
        $user->two_factor_secret = $secret;
        $user->save();

        $qrData = $this->generateQrCode($secret, $user->email);

        return response()->json([
            'status' => 'success',
            'data' => $qrData,
        ], 200);
    }

    /**
     * Genera el QR code SVG + URL manual para configurar Google Authenticator.
     *
     * @return array{qr_code: string, secret: string, manual_url: string}
     */
    private function generateQrCode(string $secret, string $email): array
    {
        $qrUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($qrUrl);

        return [
            'qr_code' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
            'secret' => $secret,
            'manual_url' => $qrUrl,
        ];
    }

    /**
     * PASO 2: Confirmar activación de 2FA.
     *
     * Requiere: código TOTP de 6 dígitos.
     * Si el código es válido:
     *  1. Marca two_factor_enabled = true
     *  2. Genera 10 backup codes (hasheados con bcrypt)
     *  3. Los guarda en two_factor_backup_codes
     *
     * Devuelve los backup codes en texto plano para que el usuario
     * los guarde. NUNCA se vuelven a mostrar.
     */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        if (! $user->verifyTwoFactorCode($validated['code'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Código inválido. Asegúrate de haber escaneado el QR correctamente.',
            ], 422);
        }

        // Activar 2FA y generar backup codes en el modelo
        $user->two_factor_enabled = true;
        $plainCodes = $user->generateBackupCodes();
        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => [
                'message' => '2FA activado correctamente. Guarda estos códigos de respaldo en un lugar seguro.',
                'backup_codes' => $plainCodes,
            ],
        ], 200);
    }

    /**
     * PASO 3: Verificar 2FA durante el login.
     *
     * Este endpoint se llama DESPUÉS de que el usuario hizo login
     * y recibió un token con ability ['2fa_pending'].
     *
     * Flujo:
     *  1. Recibe el código TOTP (6 dígitos) o backup code (8 caracteres)
     *  2. Si es TOTP: verifica con verifyTwoFactorCode()
     *  3. Si es backup code: verifica con useBackupCode()
     *  4. Revoca el token pendiente
     *  5. Emite un token completo ['*']
     *  6. Devuelve AuthResource
     */
    public function verify(VerifyTwoFactorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $code = $validated['code'];
        $isValid = false;

        // Determinar si es TOTP (6 dígitos) o backup code (8 caracteres)
        if (strlen($code) === 6 && ctype_digit($code)) {
            // Código TOTP
            $isValid = $user->verifyTwoFactorCode($code);
        } else {
            // Backup code
            $isValid = $user->useBackupCode($code);
        }

        if (! $isValid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Código inválido o expirado.',
            ], 422);
        }

        // Revocar el token pendiente (actual)
        $request->user()->currentAccessToken()->delete();

        // Revocar también cualquier otro token '2fa_pending' residual del usuario
        // (p. ej. de un login previo en otro dispositivo) para no dejarlos colgando.
        $user->tokens()->where('name', '2fa_pending')->delete();

        // Emitir token completo
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => new AuthResource($user, $token),
        ], 200);
    }

    /**
     * PASO 4: Desactivar 2FA.
     *
     * Requiere: password actual + código TOTP (doble verificación).
     * Limpia: two_factor_secret, two_factor_enabled, two_factor_backup_codes.
     */
    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        // El FormRequest ya verificó password + código TOTP via after()
        $request->user()->disableTwoFactor();

        return response()->json([
            'status' => 'success',
            'message' => '2FA desactivado correctamente.',
        ], 200);
    }
}
