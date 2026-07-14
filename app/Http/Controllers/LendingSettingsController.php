<?php

namespace App\Http\Controllers;

use App\Models\LendingSetting;
use Illuminate\Http\Request;

class LendingSettingsController extends Controller
{
    public function show()
    {
        $settings = LendingSetting::current();

        return view('admin.settings.lending', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'max_active_loans' => 'required|integer|min:1|max:10',
            'rejection_cooldown_days' => 'required|integer|min:0|max:365',
            'affordability_ratio' => 'required|numeric|min:0.05|max:0.60',
            'clo_risk_review_threshold' => 'required|integer|min:0|max:100',
            'clo_risk_escalate_threshold' => 'required|integer|min:0|max:100|gt:clo_risk_review_threshold',
            'ifrs9_stage2_dpd' => 'required|integer|min:1|max:364',
            'arrears_second_notice_dpd' => 'required|integer|min:1|max:364|gt:ifrs9_stage2_dpd',
            'ifrs9_stage3_dpd' => 'required|integer|min:1|max:364|gt:arrears_second_notice_dpd',
            'ifrs9_writeoff_dpd' => 'required|integer|min:1|max:1000|gt:ifrs9_stage3_dpd',
            'ifrs9_stage1_rate' => 'required|numeric|min:0|max:1|lt:ifrs9_stage2_rate',
            'ifrs9_stage2_rate' => 'required|numeric|min:0|max:1|lt:ifrs9_stage3_rate',
            'ifrs9_stage3_rate' => 'required|numeric|min:0|max:1',
            'payment_reminder_days_before_due' => 'required|integer|min:1|max:30',
        ]);

        $settings = LendingSetting::current();
        $settings->update($validated);

        return redirect()->route('admin.settings.lending')
            ->with('success', 'Lending & risk settings saved. Changes apply immediately — no deploy needed.');
    }
}
