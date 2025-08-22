<?php
namespace Gm2\AI;

interface ProviderInterface {
    /**
     * Sends a prompt to the AI provider and returns the response text or an error.
     *
     * @param string $prompt Prompt to send to the provider.
     * @param array  $args   Optional arguments to influence the query.
     * @return string|\WP_Error
     */
    public function query(string $prompt, array $args = []): string|\WP_Error;
}
