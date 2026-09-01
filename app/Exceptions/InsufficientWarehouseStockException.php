<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InsufficientWarehouseStockException extends RuntimeException
{
    public function __construct(
        public readonly string $itemCode,
        public readonly float $available,
        public readonly float $requested,
        public readonly ?int $warehouseId = null,
    ) {
        parent::__construct(sprintf(
            '%s cuma ada %s, mau diambil %s',
            $itemCode,
            self::formatQuantity($available),
            self::formatQuantity($requested),
        ));
    }

    /**
     * Same shape as {@see ValidationException} so AJAX forms keep their inputs
     * and populate `errors.items` / `serverErrors`.
     *
     * @return array<string, list<string>>
     */
    public function formErrors(): array
    {
        return ['items' => [$this->getMessage()]];
    }

    public function asValidationException(): ValidationException
    {
        return ValidationException::withMessages($this->formErrors());
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        return $this->toResponse($request);
    }

    public function toResponse(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => $this->formErrors(),
            ], 422);
        }

        return back()
            ->withInput()
            ->withErrors($this->formErrors())
            ->with('error', $this->getMessage());
    }

    public function report(): false
    {
        return false;
    }

    public static function formatQuantity(float $quantity): string
    {
        $formatted = number_format($quantity, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
