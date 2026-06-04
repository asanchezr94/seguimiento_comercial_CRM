<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = AppNotification::where('user_id', auth()->id())
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(string $id)
    {
        $notification = AppNotification::where('user_id', auth()->id())->findOrFail($id);
        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }
        return back()->with('ok', 'Notificacion marcada como leida.');
    }
}
