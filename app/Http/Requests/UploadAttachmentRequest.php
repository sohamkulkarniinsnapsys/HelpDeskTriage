<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        
        // User must be able to view the ticket to upload attachments
        return Gate::allows('view', $ticket);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,gif,zip',
            ],
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'attachments.*' => 'attachment',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'attachments.required' => 'Please select at least one file to upload.',
            'attachments.max' => 'You may upload a maximum of :max files at once.',
            'attachments.*.max' => 'Each file must not exceed 10MB.',
        ];
    }
}
