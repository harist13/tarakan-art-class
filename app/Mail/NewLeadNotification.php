<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifikasi ke admin saat ada calon murid mengisi form Kontak.
 */
class NewLeadNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Calon murid baru: '.$this->lead->child_name,
            replyTo: $this->lead->parent_email ? [$this->lead->parent_email] : [],
        );
    }

    public function content(): Content
    {
        // markdown (bukan view) — isi email memakai komponen <x-mail::…>.
        return new Content(markdown: 'emails.new-lead');
    }
}
