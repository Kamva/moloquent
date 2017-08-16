<?php

namespace Kamva\Moloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use MongoDB\BSON\ObjectID;

class ContainsFew extends Relation
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
            $this->query->whereIn($this->foreignKey, (array) $this->parent->{$this->localKey});
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
        $this->query->whereIn($this->foreignKey, $this->getLocalKeys($models));
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

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            if (isset($dictionary[$key = (string) $model->getKey()])) {
                $collection = $this->related->newCollection($dictionary[$key]);

                $model->setRelation($relation, $collection);
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
        return $this->query->get();
    }

    /**
     * Create a new instance of the related model and Include it in parent model.
     *
     * @param array $attributes
     * @return Model
     */
    public function create(array $attributes)
    {
        $instance = $this->related->newInstance($attributes);

        $instance->save(['touch' => false]);

        $this->attach($instance);

        return $instance;
    }

    /**
     * Include a model instance into the parent model.
     *
     * @param Model $model
     * @return Model
     */
    public function save(Model $model)
    {
        $model->save();

        $this->attach($model);

        return $model;
    }

    /**
     * Include array of model instances inside the parent model.
     *
     * @param array|Model[] $models
     * @return array|Model[]
     */
    public function saveMany(array $models)
    {
        $ids = [];
        foreach ($models as $model) {
            $model->save();
            $ids[] = $model->getKey();
        }

        $this->sync($ids, false);

        return $models;
    }

    /**
     * Include a model inside parent model.
     *
     * @param string|Model|ObjectID $id
     * @param bool                  $convert
     */
    public function attach($id, $convert = true)
    {
        $id = $this->convertOne($id, $convert);

        $this->parent->push($this->localKey, $id, true);

        $this->parent->touch();
    }

    /**
     * Exclude models from parent model.
     *
     * @param array|Model[]|ObjectID[] $ids
     * @param bool                     $convert
     */
    public function detach($ids = [], $convert = true)
    {
        $ids = $this->convertMany($ids, $convert);

        if (empty($ids)) {
            $this->parent->{$this->localKey} = [];
            $this->parent->save();
        } else {
            $this->parent->pull($this->localKey, $ids);
        }

        $this->parent->touch();
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     *
     * @param  array $ids
     * @param  bool  $detaching
     * @return array
     */
    public function sync($ids, $detaching = true)
    {
        $changes = [
            'attached' => [],
            'detached' => [],
        ];

        if ($ids instanceof Collection) {
            $ids = $ids->modelKeys();
        }

        $current = $this->parent->{$this->localKey} ?: [];

        $detach = array_diff($current, $ids);

        $detach = array_values($detach);

        if ($detaching and count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = (array) array_map(function ($v) {
                return is_numeric($v) ? (int) $v : (string) $v;
            }, $detach);
        }

        $changes = array_merge(
            $changes, $this->attachMany($ids)
        );

        if (count($changes['attached']) || count($changes['detached'])) {
            $this->parent->touch();
        }

        return $changes;
    }

    /**
     * Get the key value of the parent's local key.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Include models inside parent model.
     *
     * @param array|Model[]|ObjectID[] $ids
     * @param bool                     $convert
     * @return array
     */
    protected function attachMany($ids, $convert = true)
    {
        $changes = ['attached' => []];

        foreach ($ids as $id) {
            $id = $this->convertOne($id, $convert);
            if ($this->parent->push($this->localKey, $id, true)) {
                $changes['attached'][] = (string) $id;
            }
        }

        return $changes;
    }

    /**
     * @param string|Model|ObjectID $id
     * @param bool                  $convert
     * @return ObjectID
     */
    protected function convertOne($id, $convert = true)
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        } elseif (!$id instanceof ObjectID && $convert) {
            $id = new ObjectID($id);
        }

        return $id;
    }

    /**
     * @param $ids
     * @param $convert
     * @return array
     */
    protected function convertMany($ids, $convert = true)
    {
        if ($ids instanceof Model) {
            $ids = [$ids->getKey()];
        } elseif (!$ids instanceof ObjectID) {
            if (is_array($ids) && $convert) {
                $tmp = [];
                foreach ($ids as $id) {
                    $tmp[] = $id instanceof ObjectID ? $id : new ObjectID($id);
                }
                $ids = $tmp;
            } else {
                $ids = $convert ? [new ObjectID($ids)] : (array) $ids;
            }
        }

        return $ids;
    }

    protected function getLocalKeys($models)
    {
        $foreignKeys = [];
        foreach ($models as $model) {
            $foreignKeys = array_merge($foreignKeys, (array) $model->{$this->localKey});
        }

        return $foreignKeys;
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
            $foreignKeys = (array) $model->{$this->localKey};
            $dictionary[(string) $model->getKey()] = $results->filter(function ($value) use ($foreignKeys) {
                return in_array($value->{$this->foreignKey}, $foreignKeys);
            })->all();
        }

        return $dictionary;
    }

}
