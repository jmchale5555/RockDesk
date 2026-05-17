<?php

namespace Model;

class TicketEmailMessage
{
    use Model;

    protected $table = 'ticket_email_messages';

    protected $allowedColumns = [
        'ticket_id',
        'message_id',
        'email_type',
        'recipients',
        'created_at',
    ];

    public function findTicketIdByHeaderReference(string $references): int
    {
        preg_match_all('/<([^>]+)>|([^\s]+)/', $references, $matches);
        $messageIds = [];

        foreach ($matches[1] as $index => $bracketed)
        {
            $messageId = trim((string)($bracketed ?: $matches[2][$index]));
            if ($messageId !== '')
            {
                $messageIds[] = trim($messageId, '<>');
            }
        }

        foreach (array_unique($messageIds) as $messageId)
        {
            $row = $this->first(['message_id' => $messageId]);
            if ($row)
            {
                return (int)$row->ticket_id;
            }
        }

        return 0;
    }
}
