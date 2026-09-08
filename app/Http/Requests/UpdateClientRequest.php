<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('lastName') && ! $this->has('last_Name')) {
            $this->merge([
                'last_Name' => $this->input('lastName'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $client */
        $client = $this->route('client') ?? $this->route('user');
        $clientId = $client instanceof User ? $client->identification : $client;

        return [
            'name' => ['required', 'string', 'max:50'],
            'last_Name' => ['required', 'string', 'max:50'],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique('users', 'email')->ignore($clientId, 'identification'),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'direction' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
