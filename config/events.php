<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Event Listener Registry (Pub/Sub)
    |--------------------------------------------------------------------------
    | Map your Events (Publishers) to their Listeners (Subscribers) here.
    */
    
    // Example:
    // \App\Events\SaleCompleted::class => [
    //     \App\Listeners\DeductInventory::class,
    //     \App\Listeners\SendReceiptEmail::class,
    //     \App\Listeners\NotifyAdminsViaWebSocket::class,
    // ],

    \App\Events\OrderReady::class => [
        \App\Listeners\NotifyWaitingArea::class,
    ],

    \App\Events\NotificationSent::class => [
        \App\Listeners\SendRealtimeNotification::class,
    ],

    \App\Events\BroadcastSent::class => [
        \App\Listeners\SendRealtimeNotification::class,
    ],
    ];