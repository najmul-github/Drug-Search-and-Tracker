<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Traits\ApiResponse;

class LoginRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email' => ['required','email'],
            'password' => ['required','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Invalid email format.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        // Throws JSON response instead of redirect
        throw new HttpResponseException(
            $this->fail('Validation failed', 422, $validator->errors())
        );
    }
}
