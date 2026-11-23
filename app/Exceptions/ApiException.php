<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class ApiException extends \RuntimeException
{
    public function __construct(
        protected string $errorCode,
        string $message,
        protected int $status = 400,
        protected array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $error = [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ];

        if ($this->details !== []) {
            $error['details'] = $this->details;
        }

        return response()->json(['error' => $error], $this->status);
    }
}
