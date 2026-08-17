<?php

namespace App\Session;

use Illuminate\Session\SessionManager as BaseSessionManager;

class SessionManager extends BaseSessionManager
{
    /**
     * @param  \SessionHandlerInterface  $handler
     * @return \Illuminate\Session\Store
     */
    protected function buildSession($handler)
    {
        return $this->config->get('session.encrypt')
            ? $this->buildEncryptedSession($handler)
            : new Store(
                $this->config->get('session.cookie'),
                $handler,
                null,
                $this->config->get('session.serialization', 'php')
            );
    }

    /**
     * @param  \SessionHandlerInterface  $handler
     * @return \Illuminate\Session\EncryptedStore
     */
    protected function buildEncryptedSession($handler)
    {
        return new EncryptedStore(
            $this->config->get('session.cookie'),
            $handler,
            $this->container['encrypter'],
            null,
            $this->config->get('session.serialization', 'php'),
        );
    }
}
