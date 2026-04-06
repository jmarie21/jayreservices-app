<?php

use App\Models\SupportConversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('admin.notifications', function ($user) {
    return $user->role === 'admin'; // or whatever logic fits your app
});

Broadcast::channel('support.admin.inbox', function ($user) {
    return $user->role === 'admin';
});

Broadcast::channel('support.conversation.{conversationId}', function ($user, $conversationId) {
    if ($user->role === 'editor') {
        return false;
    }

    if ($user->role === 'admin') {
        return true;
    }

    return SupportConversation::query()
        ->whereKey((int) $conversationId)
        ->where('client_id', $user->id)
        ->exists();
});
