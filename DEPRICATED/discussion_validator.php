<?php
function validateDiscussion(array $data): array {
    $errors = [];

    if (empty($data['contact_id'])) {
        $errors['contact_id'] = 'Contact ID is required.';
    }

    if (empty($data['entry_text'])) {
        $errors['entry_text'] = 'Comment text is required.';
    }

    if (empty($data['author'])) {
        $errors['author'] = 'Author is required.';
    }

    return $errors;
}
