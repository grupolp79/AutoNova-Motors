<?php

// Mensajes de validación en español. Si una regla no está acá, Laravel usa el texto en inglés
// (fallback_locale), así que solo se traducen las reglas que usa el proyecto.
return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'url' => 'El campo :attribute debe ser una URL válida.',
    'in' => 'El valor elegido en :attribute no es válido.',
    'array' => 'El campo :attribute debe ser una lista.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',

    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
    ],

    // Nombres que se muestran en los mensajes en lugar del nombre interno del campo
    'attributes' => [
        'marca' => 'marca',
        'modelo' => 'modelo',
        'anio' => 'año',
        'precio' => 'precio',
        'kilometraje' => 'kilometraje',
        'transmision' => 'transmisión',
        'combustible' => 'combustible',
        'estado' => 'estado',
        'fotos' => 'fotos',
        'email' => 'correo electrónico',
        'password' => 'contraseña',
    ],
];
