<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class registerUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
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
        if ($this->has('Identification') && ! $this->has('identification')) {
            $this->merge([
                'identification' => $this->input('Identification'),
            ]);
        }

        if ($this->has('confirmPassword') && ! $this->has('password_confirmation')) {
            $this->merge([
                'password_confirmation' => $this->input('confirmPassword'),
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
        return [
            'identification' => ['required', 'numeric', 'unique:users,identification'],
            'name' => ['required', 'string', 'max:50'],
            'lastName' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'direction' => ['required', 'string', 'max:100'],
            'userName' => ['required', 'string', 'max:50', 'unique:users,user_name'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ];
    }
}
