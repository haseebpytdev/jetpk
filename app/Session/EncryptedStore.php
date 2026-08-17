<?php

namespace App\Session;

use Illuminate\Session\EncryptedStore as BaseEncryptedStore;

class EncryptedStore extends BaseEncryptedStore
{
    use RemarsalsJsonSessionErrorBag;
}
