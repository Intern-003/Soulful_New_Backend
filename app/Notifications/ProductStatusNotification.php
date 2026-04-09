<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductStatusNotification extends Notification
{
    use Queueable;

    protected $product;
    protected $status;

    public function __construct($product, $status)
    {
        $this->product = $product;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database']; // you can add 'broadcast' later
    }

    public function toDatabase($notifiable)
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'status' => $this->status,
            'message' => "Your product has been {$this->status}",
        ];
    }
}