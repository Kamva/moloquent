<?php
namespace Kamva\Moloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use MongoDB\BSON\ObjectID;

class ContainsOne extends Relation
{

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

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
     * @param string  $foreignKey
     * @param string  $localKey
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;

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
            $this->query->where($this->foreignKey, $this->parent->{$this->localKey});
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
        $this->query->whereIn($this->foreignKey, $this->getLocalKey($models));
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
        $dictionary = $this->buildDictionary($models, $results);

        foreach ($models as $model) {
            if (isset($dictionary[$key = (string) $model->getKey()])) {
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

    public function create(array $attributes)
    {
        $instance = $this->related->newInstance($attributes);

        $instance->save(['touch' => false]);

        $this->attach($instance);

        return $instance;
    }

    public function save(Model $model)
    {
        $model->save();

        return $this->attach($model);
    }

    public function attach($id, $convert = true)
    {
        $id = $this->convert($id, $convert);

        $this->parent->{$this->localKey} = $id;
        $this->parent->save();
    }

    public function detach($id = null, $convert = true)
    {
        $id = $this->convert($id, $convert);

        if ($this->parent->{$this->localKey} == $id || $id === null) {
            $this->parent->{$this->localKey} = null;
            $this->parent->save();
            return true;
        } else {
            return false;
        }
    }

    protected function getLocalKey($models)
    {
        $foreignKeys = [];

        foreach ($models as $model) {
            $foreignKeys[] = $model->{$this->localKey};
        }

        return $foreignKeys;
    }

    /**
     * @param $id
     * @param $convert
     * @return mixed|ObjectID
     */
    protected function convert($id, $convert = true)
    {
        if ($id instanceof Model) {
            $id = $id->getKey();

            return $id;
        } elseif ($id && !$id instanceof ObjectID && $convert) {
            $id = new ObjectID($id);

            return $id;
        }

        return $id;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param array                                     $models
     * @param  \Illuminate\Database\Eloquent\Collection $results
     * @return array
     */
    protected function buildDictionary(array $models, Collection $results)
    {
        $dictionary = [];

        foreach ($models as $model) {
            $foreignKey = $model->{$this->localKey};
            $dictionary[(string) $model->getKey()] = $results->find($foreignKey);
        }

        return $dictionary;
    }
}
