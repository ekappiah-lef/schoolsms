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
        $url = is_file($hot) ? rtrim(trim(file_get_contents($hot)), '/') : null;

        // Use the dev server only while it is actually running; a left-over public/hot
        // (dev server closed) would otherwise give a blank page.
        // Only for a browser on this same computer: phones / other PCs on the network
        // cannot reach the dev server (it listens on this machine only), so they get the build.
        $local = in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
        if ($url && $local && self::running($url)) {
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

    /** Whether the Vite dev server answers at $url (e.g. http://[::1]:5173). */
    protected static function running(string $url): bool
    {
        $p = parse_url($url);
        $host = trim($p['host'] ?? 'localhost', '[]');
        $conn = @fsockopen(strpos($host, ':') !== false ? '['.$host.']' : $host, $p['port'] ?? 5173, $errno, $errstr, 0.2);
        if (!$conn) return false;
        fclose($conn);

        return true;
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
