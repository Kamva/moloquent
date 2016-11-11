<?php
namespace Kamva\Moloquent;

use Carbon\Carbon;
use Jenssegers\Mongodb\Eloquent\HybridRelations;
use Jenssegers\Mongodb\Eloquent\Model;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDateTime;

/**
 * @method $this find(mixed $id)
 * @property Carbon created_at
 * @property Carbon updated_at
 */
abstract class Moloquent extends Model
{

    use HybridRelations;

    protected $connection = 'mongodb';

    protected $guarded = ['created_at', 'updated_at'];

    public function getKey()
    {
        return isset($this->attributes[$this->primaryKey]) ? $this->attributes[$this->primaryKey] : null;
    }

    public function toArray()
    {
        $attributes = parent::toArray();

        $this->forceToArray($attributes);
        $this->correctIdKey($attributes);

        return $attributes;
    }

    /**
     * Update the model's update timestamp.
     *
     * @return bool
     */
    public function touch()
    {
        $parentResult = parent::touch();

        $this->load(array_keys($this->relations));

        return $parentResult;
    }

    protected function forceToArray(&$attributes)
    {
        foreach ($attributes as &$attribute) {
            if (is_array($attribute)) {
                $this->forceToArray($attribute);
            } elseif ($attribute instanceof \MongoId || $attribute instanceof ObjectID) {
                $attribute = (string) $attribute;
            } elseif ($attribute instanceof \MongoDate || $attribute instanceof UTCDateTime) {
                $attribute = $attribute->toDateTime()->getTimestamp();
            }
        }
    }

    protected function correctIdKey(&$attributes)
    {
        if (isset($attributes['_id'])) {
            $attributes['id'] = $attributes['_id'];
            unset($attributes['_id']);
        }
    }

}
