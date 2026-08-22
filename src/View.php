<?php
declare(strict_types=1);

namespace App;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;

class View
{
    private static ?Environment $twig = null;

    public static function getTwig(): Environment
    {
        if (self::$twig !== null) {
            return self::$twig;
        }

        $loader = new FilesystemLoader(APP_ROOT . '/templates');
        $twig = new Environment($loader, [
            'cache' => false,
            'debug' => true,
        ]);

        $twig->addFunction(new TwigFunction('csrf_field', 'csrf_field', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('csrf_token', 'csrf_token'));
        $twig->addFunction(new TwigFunction('fmt_money', 'fmt_money'));
        $twig->addFunction(new TwigFunction('h', 'h'));
        $twig->addFilter(new TwigFilter('money', 'fmt_money'));

        self::$twig = $twig;
        return self::$twig;
    }

    public static function render(string $template, array $data = []): void
    {
        echo self::getTwig()->render($template, $data);
    }
}
