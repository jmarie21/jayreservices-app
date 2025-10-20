<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);
        
        $notification->markAsRead();

        return redirect()->back();
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back();
    }

    public function destroy(Request $request, $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);
        
        $notification->delete();

        return redirect()->back();
    }

    public function destroyAll(Request $request)
    {
        $request->user()->notifications()->delete();

        return back();
    }

}
