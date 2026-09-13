<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public demo channel: no user accounts exist in the MVP.
Broadcast::channel('simulation.{runId}', fn (): bool => true);
