<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Get all settings for the authenticated user's business
     */
    public function index()
    {
        $businessId = auth()->user()->business_id;
        
        $settings = Setting::where('business_id', $businessId)->get()->pluck('value', 'key');
        
        // Provide defaults if not set
        $defaultSettings = [
            'tax_rate' => '15',
            'business_name' => 'المُحاسِب.',
            'receipt_footer' => 'شكراً لتسوقكم معنا!',
            'currency' => 'ر.س',
        ];

        return response()->json(array_merge($defaultSettings, $settings->toArray()));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $businessId = auth()->user()->business_id;
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string'
        ]);

        foreach ($data['settings'] as $key => $value) {
            Setting::updateOrCreate(
                ['business_id' => $businessId, 'key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
