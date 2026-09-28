<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Stringable;

final class ListCompaniesRequest extends FormRequest
{
    public const int COMPANY_LIST_SEARCH_MAX_LENGTH = 255;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:'.self::COMPANY_LIST_SEARCH_MAX_LENGTH],
        ];
    }

    public function searchTerm(): ?Stringable
    {
        if (! $this->filled('search')) {
            return null;
        }

        return $this->string('search')->lower();
    }
}
