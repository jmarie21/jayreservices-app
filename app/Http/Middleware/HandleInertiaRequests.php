<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $isAdmin = $user && $user->role === 'admin';

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => fn () => $user
                    ? $user->only('id', 'name', 'email', 'role')
                    : null,
            ],
            // Only load clients and editors for admin users (lazy loaded)
            'clients' => fn () => $isAdmin
                ? User::where('role', 'client')
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                : [],
            'editors' => fn () => $isAdmin
                ? User::where('role', 'editor')
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                : [],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Optimize notification queries - limit and select only needed fields
            'notifications' => $user ? fn () => [
                'unread_count' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()
                    ->select('id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at')
                    ->latest()
                    ->take(10)
                    ->get(),
            ] : null,
        ];
    }
}
