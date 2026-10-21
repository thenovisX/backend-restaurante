<?php
declare(strict_types=1);
/* Portada. La lógica está aquí; el diseño, en tema/inicio.php. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
if (!installed()) { header('Location: install.php'); exit; }

tema_render('inicio', [
    'carta' => menu_data(false),   // ['categories' => [...con 'dishes'], 'allergens' => [...]]
]);
