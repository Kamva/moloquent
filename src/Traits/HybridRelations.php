<?php
namespace Kamva\Moloquent\Traits;

use Illuminate\Support\Str;
use Kamva\Moloquent\Moloquent;
use Kamva\Moloquent\Relations\BelongsTo;
use Kamva\Moloquent\Relations\BelongsToMany;
use Kamva\Moloquent\Relations\ContainsFew;
use Kamva\Moloquent\Relations\ContainsOne;
use Kamva\Moloquent\Relations\HasMany;
use Kamva\Moloquent\Relations\HasOne;
use Kamva\Moloquent\Relations\IncludedIn;
use Kamva\Moloquent\Relations\IncludedInMany;

trait HybridRelations
{

    /**
     * Define a one-to-one relationship.
     *
     * @param  string $related
     * @param  string $foreignKey
     * @param  string $localKey
     * @return HasOne
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, 'Jenssegers\Mongodb\Eloquent\Model')) {
            return parent::hasOne($related, $foreignKey, $localKey);
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string $related
     * @param  string $foreignKey
     * @param  string $localKey
     * @return HasMany
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, 'Jenssegers\Mongodb\Eloquent\Model')) {
            return parent::hasMany($related, $foreignKey, $localKey);
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string $related
     * @param  string $foreignKey
     * @param  string $otherKey
     * @param  string $relation
     * @return BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            list($current, $caller) = debug_backtrace(false, 2);

            $relation = $caller['function'];
        }

        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, 'Jenssegers\Mongodb\Eloquent\Model')) {
            return parent::belongsTo($related, $foreignKey, $otherKey, $relation);
        }

        $instance = new $related;

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake(class_basename($instance)) . '_id';
        }

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $query = $instance->newQuery();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param  string $related
     * @param  string $collection
     * @param  string $foreignKey
     * @param  string $otherKey
     * @param  string $relation
     * @return BelongsToMany
     */
    public function belongsToMany($related, $collection = null, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, 'Jenssegers\Mongodb\Eloquent\Model')) {
            return parent::belongsToMany($related, $collection, $foreignKey, $otherKey, $relation);
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $foreignKey = $foreignKey ?: $this->getForeignKey() . 's';

        $instance = new $related;

        $otherKey = $otherKey ?: $instance->getForeignKey() . 's';

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($collection)) {
            $collection = $instance->getTable();
        }

        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for the relation. The relations will set
        // appropriate query constraint and entirely manages the hydrations.
        $query = $instance->newQuery();

        return new BelongsToMany($query, $this, $collection, $foreignKey, $otherKey, $relation);
    }

    /**
     * Define a one-to-few relationship.
     *
     * @param  string $related
     * @param  string $localKey
     * @param  string $foreignKey
     * @return ContainsFew
     */
    public function containsFew($related, $localKey = null, $foreignKey = null)
    {
        /** @var Moloquent $instance */
        $instance = new $related;

        $localKey = $localKey ?: Str::snake(class_basename($instance)) . '_ids';

        $foreignKey = $foreignKey ?: $instance->getKeyName();

        return new ContainsFew($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-few relationship.
     *
     * @param  string $related
     * @param  string $localKey
     * @param  string $foreignKey
     * @return ContainsOne
     */
    public function containsOne($related, $localKey = null, $foreignKey = null)
    {
        /** @var Moloquent $instance */
        $instance = new $related;

        $localKey = $localKey ?: Str::snake(class_basename($instance)) . '_id';

        $foreignKey = $foreignKey ?: $instance->getKeyName();

        return new ContainsOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-few relationship.
     *
     * @param  string $related
     * @param  string $otherKey
     * @param  string $localKey
     * @return IncludedIn
     */
    public function includedIn($related, $otherKey, $localKey = null)
    {
        /** @var Moloquent $instance */
        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new IncludedIn($instance->newQuery(), $this, $otherKey, $localKey);
    }

    /**
     * Define a one-to-few relationship.
     *
     * @param  string  $related
     * @param  string  $otherKey
     * @param  string  $localKey
     * @return IncludedInMany
     */
    public function includedInMany($related, $otherKey = null, $localKey = null)
    {
        /** @var Moloquent $instance */
        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        $otherKey = $otherKey ?: Str::snake(class_basename($this)).'s';

        return new IncludedInMany($instance->newQuery(), $this, $otherKey, $localKey);
    }

}
