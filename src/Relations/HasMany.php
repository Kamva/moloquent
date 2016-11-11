<?php
namespace Kamva\Moloquent\Relations;

use Jenssegers\Mongodb\Relations\HasMany as MongoHasMany;
use Kamva\Moloquent\Traits\HasOneOrMany;

class HasMany extends MongoHasMany
{

    use HasOneOrMany;

}
