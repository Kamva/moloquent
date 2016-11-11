<?php
namespace Kamva\Moloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class IncludedIn extends Relation
{

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $otherKey;

    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * Create a new has one or many relationship instance.
     *
     * @param Builder $query
     * @param Model   $parent
     * @param string  $otherKey
     * @param string  $localKey
     */
    public function __construct(Builder $query, Model $parent, $otherKey, $localKey)
    {
        $this->localKey = $localKey;
        $this->otherKey = $otherKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->otherKey, $this->getLocalKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->query->whereIn($this->otherKey, $this->getKeys($models));
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array  $models
     * @param  string $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array                                    $models
     * @param  \Illuminate\Database\Eloquent\Collection $results
     * @param  string                                   $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            if (isset($dictionary[$key = (string) $model->{$this->localKey}])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->first();
    }

    public function attachTo($id)
    {
        $newParent = ($id instanceof Model) ? $id : $this->related->find($id);

        $currentParent = $this->getResults();

        if ($newParent == null || $currentParent == $newParent) {
            return false;
        }


        if ($currentParent != null) {
            $this->detachFrom($currentParent);
        }

        $newParent->push($this->otherKey, $this->getLocalKey(), true);
        $newParent->touch();

        return true;
    }

    public function detachFrom($id)
    {
        $parent = ($id instanceof Model) ? $id : $this->related->find($id);

        $parent->pull($this->otherKey, $this->getLocalKey());
    }

    protected function getLocalKey()
    {
        $attributes = $this->parent->getAttributes();

        if (array_key_exists($this->localKey, $attributes)) {
            return $attributes[$this->localKey];
        } else {
            return $this->parent->getAttribute($this->localKey);
        }
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];

        foreach ($results as $result) {
            foreach ($result->{$this->otherKey} as $item) {
                $dictionary[(string) $item] = $result;
            }
        }

        return $dictionary;
    }

}
