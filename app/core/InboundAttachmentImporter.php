<?php

namespace Core;

use Model\Ticket;
use Model\TicketAttachment;
use Model\TicketEvent;

defined('ROOTPATH') or exit('Access Denied');

class InboundAttachmentImporter
{
    public function __construct(
        private ?TicketAttachment $attachments = null,
        private ?Ticket $tickets = null,
        private ?TicketEvent $events = null,
    ) {
        $this->attachments ??= new TicketAttachment;
        $this->tickets ??= new Ticket;
        $this->events ??= new TicketEvent;
    }

    public function importForTicket(mixed $ticket, int $userId, array $attachments): int
    {
        if (!defined('INBOUND_MAIL_ATTACHMENTS_ENABLED') || !INBOUND_MAIL_ATTACHMENTS_ENABLED)
        {
            return 0;
        }

        $count = 0;
        foreach ($attachments as $attachment)
        {
            if (!$this->isImportable($attachment))
            {
                continue;
            }

            $saved = $this->save($ticket, $userId, $attachment);
            if ($saved)
            {
                $count++;
            }
        }

        return $count;
    }

    public function isImportable(array $attachment): bool
    {
        $content = (string)($attachment['content'] ?? '');
        $size = (int)($attachment['size'] ?? strlen($content));
        $minBytes = defined('INBOUND_MAIL_ATTACHMENT_MIN_BYTES') ? INBOUND_MAIL_ATTACHMENT_MIN_BYTES : 2048;

        if ($content === '' || $size < max(0, $minBytes) || $size > TicketAttachment::MAX_BYTES)
        {
            return false;
        }

        $mimeType = strtolower(trim((string)($attachment['mime_type'] ?? '')));
        if ($mimeType !== '' && !$this->attachments->isAllowedMimeType($mimeType))
        {
            return false;
        }

        $tmp = $this->temporaryFile($content);
        if ($tmp === '')
        {
            return false;
        }

        $detectedMime = $this->attachments->detectMimeType($tmp);
        @unlink($tmp);

        return $this->attachments->isAllowedMimeType($detectedMime);
    }

    private function save(mixed $ticket, int $userId, array $attachment): bool
    {
        $content = (string)($attachment['content'] ?? '');
        $tmp = $this->temporaryFile($content);
        if ($tmp === '')
        {
            return false;
        }

        $mimeType = $this->attachments->detectMimeType($tmp);
        @unlink($tmp);

        if (!$this->attachments->isAllowedMimeType($mimeType) || !$this->ensureStorage())
        {
            return false;
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $this->attachments->extensionForMimeType($mimeType);
        $storagePath = $this->storagePath($storedName);
        if (file_put_contents($storagePath, $content) === false)
        {
            return false;
        }

        $originalName = $this->attachments->safeOriginalName((string)($attachment['filename'] ?? 'email-image'));
        $this->attachments->insert([
            'ticket_id' => (int)$ticket->id,
            'user_id' => $userId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => strlen($content),
            'is_inline' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->tickets->update((int)$ticket->id, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->events->insert([
            'ticket_id' => (int)$ticket->id,
            'user_id' => $userId,
            'event_type' => 'attachment_uploaded',
            'new_value' => $originalName,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function temporaryFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rockdesk-inbound-');
        if ($path === false || file_put_contents($path, $content) === false)
        {
            return '';
        }

        return $path;
    }

    private function ensureStorage(): bool
    {
        $directory = dirname($this->storagePath('placeholder'));

        return is_dir($directory) || mkdir($directory, 0755, true);
    }

    private function storagePath(string $storedName): string
    {
        return ROOTPATH . '../storage/ticket-attachments/' . basename($storedName);
    }
}
