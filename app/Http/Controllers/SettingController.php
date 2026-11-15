<?php

namespace App\Http\Controllers;

use App\Models\WebsiteImage;
use App\Services\AuditLogger;
use App\Support\SettingDefinitions;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * System Settings (general, financial, SMS) and Website Management (homepage
 * content and photographs). Both edit the same settings store.
 */
class SettingController extends Controller
{
    private const IMAGE_RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'];

    public function edit(Request $request)
    {
        $tab = in_array($request->input('tab'), ['general', 'financial', 'sms'], true) ? $request->input('tab') : 'general';

        return view('admin.settings', ['tab' => $tab, 'definitions' => SettingDefinitions::all()]);
    }

    public function update(Request $request)
    {
        $group = $request->input('group');
        abort_unless(in_array($group, ['general', 'financial', 'sms'], true), 404);
        $this->save($request, $group);

        return redirect()->route('admin.settings', ['tab' => $group])->with('success', SettingDefinitions::GROUPS[$group].' settings saved.');
    }

    public function website()
    {
        return view('admin.website', [
            'definitions' => SettingDefinitions::forGroup('website'),
            'images' => WebsiteImage::orderBy('sort_order')->get(),
        ]);
    }

    public function updateWebsite(Request $request)
    {
        $this->save($request, 'website');

        return redirect()->route('admin.website')->with('success', 'Website content saved.');
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'caption' => ['nullable', 'string', 'max:150'],
        ]);
        $img = WebsiteImage::create([
            'path' => $request->file('image')->store('website', 'public'),
            'caption' => $request->input('caption'),
            'sort_order' => (int) WebsiteImage::max('sort_order') + 1,
            'uploaded_by' => auth()->id(),
        ]);
        AuditLogger::log('website.image_uploaded', $img, null, $img->only(['path', 'caption']));

        return back()->with('success', 'Photograph uploaded.');
    }

    public function updateImage(Request $request, WebsiteImage $image)
    {
        $data = $request->validate(['caption' => ['nullable', 'string', 'max:150'], 'sort_order' => ['required', 'integer', 'min:0']]);
        $image->update($data + ['is_visible' => $request->boolean('is_visible')]);

        return back()->with('success', 'Photograph updated.');
    }

    public function deleteImage(WebsiteImage $image)
    {
        Storage::disk('public')->delete($image->path);
        AuditLogger::log('website.image_deleted', $image, $image->only(['path', 'caption']), null);
        $image->delete();

        return back()->with('success', 'Photograph removed.');
    }

    private function save(Request $request, string $group): void
    {
        $defs = SettingDefinitions::forGroup($group);

        $rules = [];
        foreach ($defs as $key => [, $type, $label]) {
            $rules[$key] = match ($type) {
                'image' => $key === 'favicon' ? ['nullable', 'image', 'mimes:png,jpg,jpeg,ico', 'max:512'] : self::IMAGE_RULES,
                'number' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
                'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'boolean' => ['nullable'],
                'select' => ['nullable', 'in:'.implode(',', array_keys(SettingDefinitions::SMS_PROVIDERS))],
                'textarea' => ['nullable', 'string', 'max:5000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }
        $rules['group_name'] = $group === 'general' ? ['required', 'string', 'max:150'] : ($rules['group_name'] ?? []);
        $rules = array_filter($rules);
        foreach (['website_url', 'facebook_url', 'twitter_url', 'instagram_url'] as $url) {
            if (isset($rules[$url])) {
                $rules[$url] = ['nullable', 'url', 'max:255'];
            }
        }
        $request->validate($rules);

        $old = [];
        $new = [];
        foreach ($defs as $key => [, $type]) {
            $current = Settings::get($key);
            if ($type === 'image') {
                if ($request->boolean('remove_'.$key) && $current) {
                    Storage::disk('public')->delete($current);
                    $value = null;
                } elseif ($request->hasFile($key)) {
                    if ($current) {
                        Storage::disk('public')->delete($current);
                    }
                    $value = $request->file($key)->store('branding', 'public');
                } else {
                    continue;
                }
            } elseif ($type === 'boolean') {
                $value = $request->boolean($key) ? '1' : '0';
            } elseif ($type === 'password') {
                if (! $request->filled($key)) {
                    continue; // keep the stored secret when the field is left blank
                }
                $value = $request->input($key);
            } else {
                $value = $request->input($key);
            }

            if ((string) $current !== (string) $value) {
                $old[$key] = $current;
                $new[$key] = $value;
                Settings::set($key, $value);
            }
        }

        if ($new) {
            AuditLogger::log('settings.updated', null, $old, $new, SettingDefinitions::GROUPS[$group].' settings changed');
        }
    }
}
