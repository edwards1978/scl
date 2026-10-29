<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportarEmpleadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'max:5120',
                'extensions:ods',
                'mimetypes:application/vnd.oasis.opendocument.spreadsheet,application/zip,application/octet-stream,application/x-zip-compressed,application/x-compressed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe seleccionar un archivo .ods.',
            'archivo.file' => 'El archivo cargado no es válido.',
            'archivo.max' => 'El archivo no debe superar 5 MB.',
            'archivo.extensions' => 'El archivo debe tener extensión .ods.',
            'archivo.mimetypes' => 'El archivo debe ser una hoja de cálculo OpenDocument (.ods).',
        ];
    }
}
