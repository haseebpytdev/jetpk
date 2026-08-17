<?php

namespace App\Session;

use Illuminate\Session\Store as BaseStore;

class Store extends BaseStore
{
    use RemarsalsJsonSessionErrorBag;
}
