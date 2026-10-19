<?php

namespace App\Services;

use App\Models\FeedbackMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class FeedbackService
{
    public function submit(User $user, array $data): FeedbackMessage
    {
        $message = FeedbackMessage::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'status' => FeedbackMessage::STATUS_NEW,
        ]);

        $this->mail(
            config('support.email'),
            '[Feedback] ' . (FeedbackMessage::TYPES[$message->type] ?? $message->type) . ' — ' . $user->name,
            "From: {$user->name} ({$user->email}, {$user->phone})\n"
            . 'Type: ' . (FeedbackMessage::TYPES[$message->type] ?? $message->type) . "\n"
            . 'Subject: ' . ($message->subject ?: '-') . "\n\n" . $message->message,
        );

        return $message;
    }

    public function reply(FeedbackMessage $message, string $reply, ?User $admin = null): void
    {
        $message->update([
            'admin_reply' => $reply,
            'replied_at' => now(),
            'replied_by' => $admin?->id,
            'status' => FeedbackMessage::STATUS_ANSWERED,
        ]);

        $this->mail(
            $message->user?->email,
            'আপনার বার্তার উত্তর — ' . config('support.company'),
            "আপনার বার্তা:\n{$message->message}\n\nউত্তর:\n{$reply}",
        );
    }

    public function resolve(FeedbackMessage $message): void
    {
        $message->update(['status' => FeedbackMessage::STATUS_RESOLVED]);
    }

    /** E-mail problems must never break the page: failures are only logged. */
    private function mail(?string $to, string $subject, string $body): void
    {
        if (blank($to)) {
            return;
        }

        try {
            Mail::raw($body, fn ($mail) => $mail->to($to)->subject($subject));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
