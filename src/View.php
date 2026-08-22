<?php
declare(strict_types=1);

namespace App;

class View
{
    private static mixed $twig = null;

    public static function hasTwig(): bool
    {
        return class_exists('\Twig\Environment');
    }

    public static function getTwig(): mixed
    {
        if (!self::hasTwig()) {
            return null;
        }

        if (self::$twig !== null) {
            return self::$twig;
        }

        $loader = new \Twig\Loader\FilesystemLoader(APP_ROOT . '/templates');
        $twig = new \Twig\Environment($loader, [
            'cache' => false,
            'debug' => true,
        ]);

        $twig->addFunction(new \Twig\TwigFunction('csrf_field', 'csrf_field', ['is_safe' => ['html']]));
        $twig->addFunction(new \Twig\TwigFunction('csrf_token', 'csrf_token'));
        $twig->addFunction(new \Twig\TwigFunction('fmt_money', 'fmt_money'));
        $twig->addFunction(new \Twig\TwigFunction('h', 'h'));
        $twig->addFilter(new \Twig\TwigFilter('money', 'fmt_money'));

        self::$twig = $twig;
        return self::$twig;
    }

    public static function render(string $template, array $data = []): void
    {
        if (self::hasTwig()) {
            echo self::getTwig()->render($template, $data);
            return;
        }

        // Fallback PHP template engine for environments where vendor/twig is missing
        extract($data);
        $templateBase = str_replace('.twig', '', $template);

        // Include header
        $headerFile = APP_ROOT . '/src/layout_header.php';
        if (file_exists($headerFile)) {
            require $headerFile;
        }

        $bodyFile = APP_ROOT . "/public/$templateBase.php";
        if (file_exists($bodyFile) && realpath($bodyFile) !== realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
            require $bodyFile;
        }

        // Include footer
        $footerFile = APP_ROOT . '/src/layout_footer.php';
        if (file_exists($footerFile)) {
            require $footerFile;
        }
    }
}
