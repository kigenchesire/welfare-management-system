<?php

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Support\Facades\Storage;

/**
 * Makes the system installable as an app (PWA) on phones and computers.
 * The manifest and icons are built from System Settings (name, colours, logo),
 * so each welfare group gets its own app name and icon without code changes.
 */
class PwaController extends Controller
{
    public const ICON_SIZES = [180, 192, 512];

    public function manifest()
    {
        $theme = $this->color('primary_color', '#1f6f5c');
        $name = Settings::get('group_name', config('app.name'));

        $icons = [];
        foreach ([192, 512] as $size) {
            $icons[] = ['src' => route('pwa.icon', ['size' => $size, 'v' => $this->version()]), 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any'];
        }
        $icons[] = ['src' => route('pwa.icon', ['size' => 512, 'maskable' => 1, 'v' => $this->version()]), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];

        return response()->json([
            'id' => url('/'),
            'name' => $name,
            'short_name' => mb_substr(Settings::get('short_name') ?: $name, 0, 12),
            'description' => Settings::get('description', ''),
            'start_url' => route('dashboard', ['source' => 'app']),
            'scope' => url('/').'/',
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone'],
            'orientation' => 'any',
            'background_color' => '#ffffff',
            'theme_color' => $theme,
            'icons' => $icons,
            'categories' => ['finance', 'productivity'],
            'shortcuts' => [
                ['name' => 'Dashboard', 'url' => route('dashboard'), 'icons' => [['src' => $icons[0]['src'], 'sizes' => '192x192']]],
                ['name' => 'Notifications', 'url' => route('notifications'), 'icons' => [['src' => $icons[0]['src'], 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Square PNG icon: the uploaded logo on a white tile, or the group's initials on the brand colour. */
    public function icon(int $size)
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);
        $maskable = request()->boolean('maskable');

        $file = 'pwa/icon-'.$size.($maskable ? '-maskable' : '').'-'.$this->version().'.png';
        $disk = Storage::disk('local');
        if (! $disk->exists($file)) {
            $disk->put($file, $this->render($size, $maskable));
        }

        return response($disk->get($file), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    public function offline()
    {
        return view('pwa.offline');
    }

    /** Changes whenever the name, colour or logo changes, so installed apps pick up the new icon. */
    private function version(): string
    {
        return substr(sha1(implode('|', [
            Settings::get('group_name'), Settings::get('short_name'), Settings::get('primary_color'), Settings::get('logo'),
        ])), 0, 10);
    }

    private function render(int $size, bool $maskable): string
    {
        $img = imagecreatetruecolor($size, $size);
        imagesavealpha($img, true);
        [$r, $g, $b] = $this->rgb($this->color('primary_color', '#1f6f5c'));
        $brand = imagecolorallocate($img, $r, $g, $b);
        $white = imagecolorallocate($img, 255, 255, 255);

        $logo = $this->loadLogo();
        imagefill($img, 0, 0, $logo ? $white : $brand);

        // Maskable icons may be cropped to a circle by Android: keep content inside the middle 80%.
        $pad = (int) round($size * ($maskable ? 0.2 : 0.1));
        $box = $size - 2 * $pad;

        if ($logo) {
            $w = imagesx($logo);
            $h = imagesy($logo);
            $scale = min($box / $w, $box / $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            imagecopyresampled($img, $logo, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
            imagedestroy($logo);
        } else {
            $this->drawInitials($img, $size, $box, $white);
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private function drawInitials($img, int $size, int $box, $color): void
    {
        $text = mb_strtoupper(mb_substr(preg_replace('/\s+/', '', (string) (Settings::get('short_name') ?: $this->initials(Settings::get('group_name', 'W')))), 0, 3));
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        if (! function_exists('imagettftext') || ! is_file($font)) {
            $fw = imagefontwidth(5) * strlen($text);
            imagestring($img, 5, (int) (($size - $fw) / 2), (int) (($size - imagefontheight(5)) / 2), $text, $color);

            return;
        }

        // Largest font size that fits the safe box.
        $pt = $box * 0.6;
        do {
            $bb = imagettfbbox($pt, 0, $font, $text);
            $tw = $bb[2] - $bb[0];
            $th = $bb[1] - $bb[7];
            $pt -= 2;
        } while (($tw > $box * 0.9 || $th > $box * 0.6) && $pt > 6);

        $x = (int) (($size - $tw) / 2 - $bb[0]);
        $y = (int) (($size + $th) / 2 - $bb[1]);
        imagettftext($img, $pt + 2, 0, $x, $y, $color, $font, $text);
    }

    private function loadLogo()
    {
        $path = Settings::get('logo');
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $img = @imagecreatefromstring(Storage::disk('public')->get($path));

        return $img ?: null;
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(3)->implode('');
    }

    private function color(string $key, string $fallback): string
    {
        $c = (string) Settings::get($key, $fallback);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $fallback;
    }

    private function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }
}
