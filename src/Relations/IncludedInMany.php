<?php
namespace Kamva\Moloquent\Relations;

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

}
