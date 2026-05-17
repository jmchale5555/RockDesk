<?php

namespace Core;

use Model\User;

defined('ROOTPATH') or exit('Access Denied');

class TicketNotifier
{
    public function __construct(
        private ?Mailer $mailer = null,
        private ?User $users = null
    ) {
        $this->mailer ??= new Mailer;
        $this->users ??= new User;
    }

    public function notifyTicketCreated(mixed $ticket, mixed $actor): void
    {
        $recipients = $this->cleanRecipients($this->users->listActiveStaffWithEmail() ?: [], (int)($actor->id ?? 0));
        $this->sendTicketMail($recipients, $ticket, 'New support ticket created', [
            'A new support ticket has been submitted.',
            'Requester: ' . (string)($ticket->requester_name ?? $actor->name ?? 'Unknown'),
            'Status: ' . $this->label((string)($ticket->status ?? 'new')),
        ]);
    }

    public function notifyStaffReply(mixed $ticket, mixed $actor, string $body): void
    {
        $recipient = $this->requesterRecipient($ticket, (int)($actor->id ?? 0));
        $this->sendTicketMail($recipient, $ticket, 'New reply on your support ticket', [
            (string)($actor->name ?? 'Support') . ' replied to your ticket.',
            $this->summary($body),
        ]);
    }

    public function notifyUserReply(mixed $ticket, mixed $actor, string $body): void
    {
        $recipients = [];
        $assignedTo = (int)($ticket->assigned_to ?? 0);

        if ($assignedTo > 0)
        {
            $assignee = $this->users->findActiveUserWithEmail($assignedTo);
            $recipients = $assignee ? [$assignee] : [];
        }
        else
        {
            $recipients = $this->users->listActiveStaffWithEmail() ?: [];
        }

        $recipients = $this->cleanRecipients($recipients, (int)($actor->id ?? 0));
        $this->sendTicketMail($recipients, $ticket, 'Requester replied to a support ticket', [
            (string)($actor->name ?? 'The requester') . ' replied to a ticket.',
            $this->summary($body),
        ]);
    }

    public function notifyAssignmentChanged(mixed $ticket, int $assignedTo, mixed $actor): void
    {
        if ($assignedTo < 1)
        {
            return;
        }

        $assignee = $this->users->findActiveUserWithEmail($assignedTo);
        $recipients = $this->cleanRecipients($assignee ? [$assignee] : [], (int)($actor->id ?? 0));
        $this->sendTicketMail($recipients, $ticket, 'Support ticket assigned to you', [
            'A support ticket has been assigned to you.',
            'Assigned by: ' . (string)($actor->name ?? 'Unknown'),
        ]);
    }

    public function notifyResolved(mixed $ticket, mixed $actor, string $body): void
    {
        $recipient = $this->requesterRecipient($ticket, (int)($actor->id ?? 0));
        $this->sendTicketMail($recipient, $ticket, 'Your support ticket was resolved', [
            'Your support ticket has been marked resolved.',
            $this->summary($body),
        ]);
    }

    public function cleanRecipients(array $users, int $excludeUserId = 0): array
    {
        $recipients = [];
        $seen = [];

        foreach ($users as $user)
        {
            $id = (int)(is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0));
            $email = trim((string)(is_array($user) ? ($user['email'] ?? '') : ($user->email ?? '')));
            $name = trim((string)(is_array($user) ? ($user['name'] ?? '') : ($user->name ?? '')));
            $key = strtolower($email);

            if (($excludeUserId > 0 && $id === $excludeUserId) || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$key]))
            {
                continue;
            }

            $seen[$key] = true;
            $recipients[] = ['id' => $id, 'name' => $name, 'email' => $email];
        }

        return $recipients;
    }

    private function requesterRecipient(mixed $ticket, int $excludeUserId = 0): array
    {
        return $this->cleanRecipients([
            [
                'id' => (int)($ticket->user_id ?? 0),
                'name' => (string)($ticket->requester_name ?? ''),
                'email' => (string)($ticket->requester_email ?? ''),
            ],
        ], $excludeUserId);
    }

    private function sendTicketMail(array $recipients, mixed $ticket, string $subject, array $lines): void
    {
        if (empty($recipients))
        {
            return;
        }

        $ticketNumber = (string)($ticket->ticket_number ?? 'Ticket');
        $ticketSubject = (string)($ticket->subject ?? 'Support ticket');
        $url = ROOT . '/tickets/show/' . (int)($ticket->id ?? 0);
        $htmlLines = array_map(fn ($line) => '<p>' . esc($line) . '</p>', array_filter($lines));
        $html = '<h1>' . esc($ticketNumber . ': ' . $ticketSubject) . '</h1>'
            . implode('', $htmlLines)
            . '<p><a href="' . esc($url) . '">View ticket</a></p>';
        $text = $ticketNumber . ': ' . $ticketSubject . "\n\n"
            . implode("\n\n", array_filter($lines))
            . "\n\nView ticket: " . $url;

        $this->mailer->send($recipients, '[' . APP_NAME . '] ' . $subject . ': ' . $ticketNumber, $html, $text);
    }

    private function summary(string $body): string
    {
        $plain = rich_text_to_plain_text($body);

        if ($plain === '')
        {
            return '';
        }

        return mb_strlen($plain) > 500 ? mb_substr($plain, 0, 497) . '...' : $plain;
    }

    private function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
