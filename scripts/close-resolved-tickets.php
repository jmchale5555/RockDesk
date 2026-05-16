<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/config.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/core/Model.php';
require __DIR__ . '/../app/models/Ticket.php';
require __DIR__ . '/../app/models/TicketEvent.php';

use Model\Ticket;
use Model\TicketEvent;

$ticket = new Ticket;
$event = new TicketEvent;
$days = TICKET_AUTO_CLOSE_DAYS;
$resolvedTickets = $ticket->listResolvedReadyToClose($days) ?: [];
$closedCount = 0;

foreach ($resolvedTickets as $row)
{
    $ticket->update((int)$row->id, $ticket->autoCloseUpdateData());
    $event->insert([
        'ticket_id' => (int)$row->id,
        'user_id' => null,
        'event_type' => 'closed_automatically',
        'old_value' => 'resolved',
        'new_value' => 'closed',
        'body' => "Auto-closed after {$days} days resolved.",
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $closedCount++;
    echo "closed {$row->ticket_number}\n";
}

echo "auto-close complete: {$closedCount} ticket(s) closed\n";
