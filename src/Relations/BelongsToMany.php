<?php
namespace Kamva\Moloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use Jenssegers\Mongodb\Eloquent\Model;
use Jenssegers\Mongodb\Relations\BelongsToMany as MongoBelongsToMany;
use MongoDB\BSON\ObjectID;

class BelongsToMany extends MongoBelongsToMany
{

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

        // Once we have an array dictionary of child objects we can easily match the
        // children back to their parent using the dictionary and the keys on the
        // the parent models. Then we will return the hydrated models back out.
        foreach ($models as $model) {
            if (isset($dictionary[$key = (string) $model->getKey()])) {
                $collection = $this->related->newCollection($dictionary[$key]);

                $model->setRelation($relation, $collection);
            }
        }

        return $models;
    }

    /**
     * Attach a model to the parent.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @param  bool   $touch
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        if ($id instanceof Model) {
            $model = $id;

            $id = $model->getKey();

            // Attach the new parent id to the related model.
            $model->push($this->foreignKey, $this->parent->getKey(), true);
        } elseif ($id instanceof ObjectID) {
            $query = $this->newRelatedQuery();

            $query->whereIn($this->related->getKeyName(), [$id]);

            // Attach the new parent id to the related model.
            $query->push($this->foreignKey, $this->parent->getKey(), true);
        } else {
            $id = new ObjectID($id);

            $query = $this->newRelatedQuery();

            $query->whereIn($this->related->getKeyName(), [$id]);

            // Attach the new parent id to the related model.
            $query->push($this->foreignKey, $this->parent->getKey(), true);

            $query = $this->newRelatedQuery();

            $query->whereIn($this->related->getKeyName(), (array) $id);

            // Attach the new parent id to the related model.
            $query->push($this->foreignKey, $this->parent->getKey(), true);
        }

        // Attach the new ids to the parent model.
        $this->parent->push($this->otherKey, [$id], true);

        if ($touch) {
            $this->touchIfTouching();
        }
    }

    /**
     * Detach models from the relationship.
     *
     * @param  int|array  $ids
     * @param  bool  $touch
     * @return int
     */
    public function detach($ids = [], $touch = true)
    {
        if ($ids instanceof Model) {
            $ids = [$ids->getKey()];
        } elseif (!$ids instanceof ObjectID) {
            if (is_array($ids)) {
                $tmp = [];
                foreach ($ids as $id) {
                    $tmp[] = $id instanceof ObjectID ? $id : new ObjectID($id);
                }
                $ids = $tmp;
            } else {
                $ids = new ObjectID($ids);
            }
        }

        $query = $this->newRelatedQuery();

        // If associated IDs were passed to the method we will only delete those
        // associations, otherwise all of the association ties will be broken.
        // We'll return the numbers of affected rows when we do the deletes.
        $ids = is_array($ids) ? $ids : [$ids];

        // Detach all ids from the parent model.
        if (empty($ids)) {
            $this->parent->{$this->otherKey} = [];
            $this->parent->save();
        } else {
            $this->parent->pull($this->otherKey, $ids);
        }

        // Prepare the query to select all related objects.
        if (count($ids) > 0) {
            $query->whereIn($this->related->getKeyName(), $ids);
        }

        // Once we have all of the conditions set on the statement, we are ready
        // to run the delete on the pivot table. Then, if the touch parameter
        // is true, we will go ahead and touch all related models to sync.
        $results = $query->pull($this->foreignKey, $this->parent->getKey());

        if ($touch) {
            $this->touchIfTouching();
        }

        return $results;
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     *
     * @param  array  $ids
     * @param  bool   $detaching
     * @return array
     */
    public function sync($ids, $detaching = true)
    {
        $changes = [
            'attached' => [], 'detached' => [], 'updated' => [],
        ];

        if ($ids instanceof Collection) {
            $ids = $ids->modelKeys();
        }

        // First we need to attach any of the associated models that are not currently
        // in this joining table. We'll spin through the given IDs, checking to see
        // if they exist in the array of current ones, and if not we will insert.
        $current = $this->parent->{$this->otherKey} ?: [];

        // See issue #256.
        if ($current instanceof Collection) {
            $current = $ids->modelKeys();
        }

        $records = $this->formatSyncList($ids);

        $detach = array_diff($current, array_keys($records));

        // We need to make sure we pass a clean array, so that it is not interpreted
        // as an associative array.
        $detach = array_values($detach);

        // Next, we will take the differences of the currents and given IDs and detach
        // all of the entities that exist in the "current" array but are not in the
        // the array of the IDs given to the method which will complete the sync.
        if ($detaching and count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = (array) array_map(function ($v) {
                return is_numeric($v) ? (int) $v : (string) $v;
            }, $detach);
        }

        // Now we are finally ready to attach the new records. Note that we'll disable
        // touching until after the entire operation is complete so we don't fire a
        // ton of touch operations until we are totally done syncing the records.
        $changes = array_merge(
            $changes, $this->attachNew($records, $current, false)
        );

        if (count($changes['attached']) || count($changes['updated'])) {
            $this->touchIfTouching();
        }

        return $changes;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $foreign = $this->foreignKey;

        // First we will build a dictionary of child models keyed by the foreign key
        // of the relation so that we will easily and quickly match them to their
        // parents without having a possibly slow inner loops for every models.
        $dictionary = [];

        foreach ($results as $result) {
            foreach ($result->$foreign as $item) {
                $dictionary[(string) $item][] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * Format the sync list so that it is keyed by ID.
     *
     * @param  array  $records
     * @return array
     */
    protected function formatSyncList(array $records)
    {
        $results = [];

        foreach ($records as $id => $attributes) {
            if (! is_array($attributes)) {
                list($id, $attributes) = [$attributes, []];
            }

            $results[(string) $id] = $attributes;
        }

        return $results;
    }

}
