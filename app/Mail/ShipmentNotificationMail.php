<?php

namespace App\Mail;

use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly UserNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[CargoTrace] '.$this->notification->title);
    }

    public function content(): Content
    {
        $url = $this->notification->shipment_id
            ? route('shipments.show', $this->notification->shipment_id)
            : route('dashboard');

        return new Content(
            view: 'emails.notification',
            with: ['notification' => $this->notification, 'url' => $url],
        );
    }
}
