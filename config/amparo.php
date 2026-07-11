<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usuarios habilitados
    |--------------------------------------------------------------------------
    |
    | Nombres de usuario que pueden registrarse, separados por coma en la
    | variable de entorno ALLOWED_USERNAMES. Con la lista vacía el registro
    | queda cerrado. Se comparan en minúsculas.
    |
    */

    'allowed_usernames' => array_values(array_filter(array_map(
        fn (string $username) => strtolower(trim($username)),
        explode(',', (string) env('ALLOWED_USERNAMES', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Zona horaria de la casa
    |--------------------------------------------------------------------------
    |
    | La app guarda timestamps en la zona por defecto de Laravel (UTC), pero
    | lo que depende de "qué día es hoy" para las personas — el puzzle del día
    | de Juegos y su racha — usa esta zona, para que el día no cambie a las
    | 21:00 de Argentina.
    |
    */

    'zona_horaria' => env('AMPARO_ZONA_HORARIA', 'America/Argentina/Buenos_Aires'),

];
