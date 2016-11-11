<?php
namespace Kamva\Moloquent\Relations;

use Jenssegers\Mongodb\Relations\HasOne as MongoHasOne;
use Kamva\Moloquent\Traits\HasOneOrMany;

class HasOne extends MongoHasOne
{

    use HasOneOrMany;

}
