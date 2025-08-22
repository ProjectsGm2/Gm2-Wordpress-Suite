<?php
namespace Gm2\AI;

interface ProviderInterface {
    /**
     * Sends a prompt to the AI provider and returns the response text or an error.
     *
     * @param string $prompt Prompt to send to the provider.
     * @return string|\WP_Error
     */
    public function query(string $prompt): string|\WP_Error;
}
