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
        $twig = self::getTwig();
        if ($twig !== null) {
            echo $twig->render($template, $data);
            return;
        }

        // Fallback template engine compiling .twig files directly when Twig environment is not loaded
        self::renderFallback($template, $data);
    }

    private static function renderFallback(string $template, array $data): void
    {
        $filePath = APP_ROOT . '/templates/' . $template;
        if (!file_exists($filePath)) {
            echo "Template not found: " . h($template);
            return;
        }

        $content = file_get_contents($filePath);

        // Process include tags
        $content = preg_replace_callback('/\{%\s*include\s+[\'"]([^\'"]+)[\'"]\s*%\}/', function($m) use ($data) {
            $subPath = APP_ROOT . '/templates/' . $m[1];
            return file_exists($subPath) ? file_get_contents($subPath) : '';
        }, $content);

        // Simple token substitutions for fallback rendering
        $phpCode = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', function($matches) {
            $expr = trim($matches[1]);
            if (preg_match('/^([a-zA-Z0-9_\.]+)\|money$/', $expr, $m)) {
                return '<?= fmt_money($this->fetch(' . var_export($m[1], true) . ', $context)) ?>';
            }
            if (preg_match('/^csrf_field\(\)$/', $expr)) {
                return '<?= csrf_field() ?>';
            }
            if (preg_match('/^csrf_token\(\)$/', $expr)) {
                return '<?= csrf_token() ?>';
            }
            return '<?= h((string)($this->fetch(' . var_export($expr, true) . ', $context) ?? "")) ?>';
        }, $content);

        $context = $data;
        $fetcher = new class {
            public function fetch(string $key, array $ctx) {
                $parts = explode('.', $key);
                $curr = $ctx;
                foreach ($parts as $p) {
                    if (is_array($curr) && array_key_exists($p, $curr)) {
                        $curr = $curr[$p];
                    } else {
                        return null;
                    }
                }
                return $curr;
            }
        };

        // Execute compiled fallback PHP
        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($tmp, $phpCode);
        (function() use ($tmp, $context, $fetcher) {
            include $tmp;
        })();
        @unlink($tmp);
    }
}
