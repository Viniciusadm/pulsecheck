<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('monitors', fn (User $user): bool => true);
