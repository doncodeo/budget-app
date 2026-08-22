<?php
declare(strict_types=1);

namespace App;

class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data);

        $base = str_replace('.twig', '', $template);
        $viewFile = APP_ROOT . "/views/{$base}.php";

        if (file_exists($viewFile)) {
            require $viewFile;
            return;
        }

        echo "View file not found: views/{$base}.php";
    }
}
