<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BulkAnnouncementMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BulkNotificationController extends Controller
{
    /**
     * Send bulk announcement email to all clients
     */
    public function sendToAllClients(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        // Get all users with client role
        $clients = User::where('role', 'client')->get();

        if ($clients->isEmpty()) {
            return redirect()->back()->with('error', 'No clients found to send notifications.');
        }

        $totalEmailsSent = 0;
        $failedEmails = [];

        foreach ($clients as $client) {
            // Get all emails for this client (primary + additional)
            $emails = $client->getAllEmails();

            foreach ($emails as $email) {
                try {
                    Mail::to($email)->queue(
                        new BulkAnnouncementMail(
                            $validated['subject'],
                            $validated['message'],
                            $client->name
                        )
                    );
                    $totalEmailsSent++;
                } catch (\Exception $e) {
                    $failedEmails[] = $email;
                    Log::error("Failed to send bulk email to {$email}: " . $e->getMessage());
                }
            }
        }

        $message = "Announcement sent to {$totalEmailsSent} email addresses across {$clients->count()} clients.";
        
        if (!empty($failedEmails)) {
            $message .= " Failed to send to: " . implode(', ', $failedEmails);
            return redirect()->back()->with('warning', $message);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Get count of clients and total emails that will receive the notification
     */
    public function getClientEmailStats()
    {
        $clients = User::where('role', 'client')->get();
        
        $totalEmails = 0;
        foreach ($clients as $client) {
            $totalEmails += count($client->getAllEmails());
        }

        return response()->json([
            'clientsCount' => $clients->count(),
            'totalEmails' => $totalEmails,
        ]);
    }
}
