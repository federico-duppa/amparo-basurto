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

];
