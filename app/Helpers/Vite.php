<?php

namespace App\Helpers;

use Illuminate\Support\HtmlString;

/**
 * Minimal stand-in for Laravel 9's @vite directive (not available on Laravel 8).
 * Reads public/hot while `npm run dev` is running, otherwise public/build/manifest.json.
 */
class Vite
{
    public static function tags(string $entry): HtmlString
    {
        $hot = public_path('hot');

        if (is_file($hot)) {
            $url = rtrim(trim(file_get_contents($hot)), '/');

            return new HtmlString(implode("\n", [
                '<script type="module">'
                    .'import RefreshRuntime from "'.$url.'/@react-refresh";'
                    .'RefreshRuntime.injectIntoGlobalHook(window);'
                    .'window.$RefreshReg$ = () => {};'
                    .'window.$RefreshSig$ = () => (type) => type;'
                    .'window.__vite_plugin_react_preamble_installed__ = true;'
                .'</script>',
                '<script type="module" src="'.$url.'/@vite/client"></script>',
                '<script type="module" src="'.$url.'/'.$entry.'"></script>',
            ]));
        }

        $manifest = self::manifest();
        if (! isset($manifest[$entry])) {
            throw new \RuntimeException("Vite entry [{$entry}] not found. Run `npm run build`.");
        }

        $chunk = $manifest[$entry];
        $tags = [];

        foreach ($chunk['css'] ?? [] as $css) {
            $tags[] = '<link rel="stylesheet" href="'.asset('build/'.$css).'">';
        }
        foreach ($chunk['imports'] ?? [] as $import) {
            if (isset($manifest[$import]['file'])) {
                $tags[] = '<link rel="modulepreload" href="'.asset('build/'.$manifest[$import]['file']).'">';
            }
        }
        $tags[] = '<script type="module" src="'.asset('build/'.$chunk['file']).'"></script>';

        return new HtmlString(implode("\n", $tags));
    }

    /** Asset version used by Inertia to force a reload after a new build. */
    public static function version(): ?string
    {
        $path = public_path('build/manifest.json');

        return is_file($path) ? md5_file($path) : null;
    }

    protected static function manifest(): array
    {
        static $manifest;

        if ($manifest === null) {
            $path = public_path('build/manifest.json');
            $manifest = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        }

        return $manifest;
    }
}
