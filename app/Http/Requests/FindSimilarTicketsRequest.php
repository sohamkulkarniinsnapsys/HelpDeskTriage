<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FindSimilarTicketsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Anyone authenticated can check for similar tickets.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255', 'min:3'],
            'description' => ['required', 'string', 'max:10000', 'min:10'],
            'category' => ['sometimes', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'subject' => 'ticket subject',
            'description' => 'ticket description',
            'category' => 'ticket category',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Please provide a subject to search for similar tickets.',
            'subject.min' => 'Subject must be at least 3 characters.',
            'description.required' => 'Please provide a description to search for similar tickets.',
            'description.min' => 'Description must be at least 10 characters.',
        ];
    }
}
