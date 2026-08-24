<?php

namespace App\Mail\Concerns;

/**
 * Permite que una plantilla muestre a quién se habría enviado el correo
 * cuando el sistema está en modo "override".
 */
trait HasOverrideNotice
{
    public bool $isOverride = false;
    public string $originalRecipient = '';

    public function applyOverrideNotice(string $originalRecipient): void
    {
        $this->isOverride = true;
        $this->originalRecipient = $originalRecipient;

        $this->with([
            'isOverride' => true,
            'originalRecipient' => $originalRecipient,
        ]);
    }
}
