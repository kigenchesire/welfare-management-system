<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\Member;
use App\Models\SmsLog;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\SmsService;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Announcements, internal notifications and SMS logs. */
class CommunicationController extends Controller
{
    // ---------- Notifications (every signed-in user) ----------

    public function notifications(Request $request)
    {
        return view('communication.notifications', [
            'notifications' => AppNotification::where('user_id', $request->user()->id)->latest()->paginate(30),
        ]);
    }

    public function readNotification(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => now()]);

        return $notification->url ? redirect($notification->url) : back();
    }

    public function readAll(Request $request)
    {
        AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    // ---------- Announcements ----------

    public function announcements()
    {
        return view('communication.announcements', ['announcements' => Announcement::with('creator')->latest()->get()]);
    }

    public function storeAnnouncement(Request $request, SmsService $sms)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:600'],
        ]);
        $members = Member::active()->get();
        $announcement = Announcement::create($data + [
            'sent_sms' => $request->boolean('send_sms'),
            'recipients' => $members->count(),
            'created_by' => auth()->id(),
        ]);

        NotificationService::toAllMembers('announcement', $data['title'], $data['body'], route('member.dashboard'));
        NotificationService::toRoles(['Administrator', 'Chairperson', 'Secretary', 'Treasurer'], 'announcement', $data['title'], $data['body']);

        if ($request->boolean('send_sms')) {
            $group = Settings::get('short_name') ?: Settings::get('group_name');
            $sms->sendLater($members->map(fn ($m) => [$m->phone, $group.': '.$data['title'].'. '.$data['body'], $m])->all(), 'announcement');
        }
        AuditLogger::log('announcement.created', $announcement, null, $data + ['sms' => $request->boolean('send_sms')]);

        return back()->with('success', 'Announcement sent to '.$members->count().' member(s).');
    }

    // ---------- SMS ----------

    public function smsLogs(Request $request)
    {
        $logs = SmsLog::with('member')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
            ->latest()->paginate(50)->withQueryString();

        return view('communication.sms-logs', compact('logs'));
    }

    public function testSms(Request $request, SmsService $sms)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $log = $sms->send($data['phone'], 'Test message from '.Settings::get('group_name').'.', 'test');

        return back()->with($log && $log->status === 'sent' ? 'success' : 'error',
            'Test SMS status: '.($log->status ?? 'invalid number').($log && $log->status !== 'sent' ? ' - '.$log->response : ''));
    }
}
