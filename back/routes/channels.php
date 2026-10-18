<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}.monitors', fn (User $user, int $userId): bool => $user->id === $userId);
