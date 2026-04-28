<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($this->shouldReturnJsonError($request)) {
            $status = $this->resolveHttpStatus($e);
            $message = $this->resolveErrorMessage($e, $status);

            $payload = [
                'success' => false,
                'message' => $message,
            ];

            if ($e instanceof ValidationException) {
                $payload['errors'] = $e->errors();
            }

            return response()->json($payload, $status);
        }

        return parent::render($request, $e);
    }

    protected function shouldReturnJsonError(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->wantsJson()
            || $request->is('modules/*/capture');
    }

    protected function resolveHttpStatus(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof AuthenticationException) {
            return 401;
        }

        if ($e instanceof TokenMismatchException) {
            return 419;
        }

        return 500;
    }

    protected function resolveErrorMessage(Throwable $e, int $status): string
    {
        $message = $e->getMessage();

        if (str_contains(strtolower($message), 'accurate_token_invalid')) {
            return 'Sesi Accurate habis atau token tidak valid. Silakan login Accurate ulang.';
        }

        if ($e instanceof ValidationException) {
            return 'Data request tidak valid.';
        }

        if ($e instanceof AuthenticationException) {
            return 'Sesi login berakhir. Silakan login kembali.';
        }

        if ($e instanceof TokenMismatchException || $status === 419) {
            return 'Sesi/CSRF token tidak valid atau sudah expired. Silakan refresh halaman lalu coba lagi.';
        }

        if ($status === 403) {
            return 'Akses ditolak untuk aksi ini.';
        }

        if ($status === 404) {
            return 'Endpoint tidak ditemukan.';
        }

        if ($status >= 500) {
            return 'Terjadi error di server saat memproses capture data.';
        }

        return $message !== '' ? $message : 'Terjadi kesalahan saat memproses request.';
    }
}
