<?php
namespace Kamva\Moloquent\Relations;

use Illuminate\Database\Eloquent\Model;

class IncludedInMany extends IncludedIn
{

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
     * Exclude current model from given parent model.
     *
     * @param $id
     */
    public function detachFrom($id)
    {
        $parent = ($id instanceof Model) ? $id : $this->related->find($id);

        $this->pullOff($parent);
    }

}
