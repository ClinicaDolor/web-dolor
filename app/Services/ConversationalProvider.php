<?php
namespace App\Services;

/** Provider boundary for conversational interpretation; persistence stays outside this contract. */
interface ConversationalProvider
{
    public function interpretConversation(array $input): array;
}
