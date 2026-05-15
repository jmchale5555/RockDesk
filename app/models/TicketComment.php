<?php

namespace Model;

class TicketComment
{
    use Model;

    protected $table = 'ticket_comments';

    protected $allowedColumns = [
        'ticket_id',
        'user_id',
        'body',
        'is_internal',
        'created_at',
        'updated_at',
    ];

    public function validateCreate(array $data): bool
    {
        $this->errors = [];

        if (empty($data['ticket_id']) || (int)$data['ticket_id'] < 1)
        {
            $this->errors['ticket_id'] = 'A ticket is required';
        }

        if (empty($data['user_id']) || (int)$data['user_id'] < 1)
        {
            $this->errors['user_id'] = 'A commenter is required';
        }

        if (empty(trim((string)($data['body'] ?? ''))))
        {
            $this->errors['body'] = 'Comment is required';
        }

        if (isset($data['is_internal']) && !in_array((int)$data['is_internal'], [0, 1], true))
        {
            $this->errors['is_internal'] = 'Choose a valid visibility';
        }

        return empty($this->errors);
    }
}
