<?php

declare(strict_types=1);

namespace CodeIgniter\Config;

class Debug extends BaseConfig
{
    /**
     * A list of environments where debugging features
     * should be disabled.
     */
    public array $forbiddenEnvironments = ['production'];

    public int $dumpMaxDepth = 7;
    public int $dumpMaxStringLength = 512;
    public int $dumpMaxItems = 50;
    public bool $dumpSafeMode = false;  // no magic invocations, limited traversal

    /**
     * A list of keys or patterns that should be redacted
     * from debug output.
     */
    public array $redactKeys = [
        'password',
        'token',
        'secret',
        'authorization',
        'cookie',
        'apiKey',
    ];

    /**
     * Determiners if a given key or path should be redacted
     * using the redactKeys configuration.
     */
    public function shouldRedact(string $keyOrPath): bool
    {
        $key = strtolower($keyOrPath);

        foreach ($this->redactKeys as $pattern) {
            if (@preg_match($pattern, '') !== false) {
                if (preg_match($pattern, $key)) return true;
            } elseif (str_contains($key, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }
}
